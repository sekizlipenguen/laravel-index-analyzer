<?php

namespace SekizliPenguen\IndexAnalyzer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DetectMissingIndexes extends Command
{
    protected $signature = 'optimize:index {--execute : Automatically apply missing indexes} {--sql= : Export SQL to file}';
    protected $description = 'Scan queries and suggest or create missing indexes';
    protected string $currentFilePath = '';

    public function handle(): void
    {
        $this->info('🔍 SQL log bazlı index analizi başlatılıyor...');

        if (!config('index-analyzer.enabled')) {
            $this->info('Index Analyzer yapılandırma tarafından devre dışı bırakılmış.');
            return;
        }

        $configPath = config('index-analyzer.scan_path') === 'base_path'
            ? base_path()
            : base_path(config('index-analyzer.scan_path'));

        $excludedDirs = config('index-analyzer.exclude', []);

        $phpFiles = collect(File::allFiles($configPath))
            ->reject(fn($file) => collect($excludedDirs)->contains(fn($dir) => str_contains($file->getPath(), $dir)))
            ->filter(fn($file) => $file->getExtension() === 'php')
            ->values();

        $getAllModelClasses = $this->getAllModelClasses();

        $this->generateTemporaryQueryScripts($phpFiles, $getAllModelClasses);
        $this->info("🚀 Geçici SQL analiz dosyaları oluşturuldu. Artık log'ları çalıştırarak analiz edebilirsin.");
    }

    /**
     * Scan app/Models and app directories for PHP files, extract namespace and class name,
     * and return an array of fully qualified model class names that are subclasses of Eloquent Model.
     *
     * @return array
     */
    protected function getAllModelClasses(): array
    {
        $paths = array_map(fn($path) => base_path(trim($path, '/')), config('index-analyzer.model_paths', []));

        $models = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;

            foreach (File::allFiles($path) as $file) {
                $content = file_get_contents($file->getRealPath());

                if (preg_match('/namespace\s+(.*?);/', $content, $namespaceMatch) &&
                    preg_match('/class\s+(\w+)/', $content, $classMatch)) {

                    $namespace = trim($namespaceMatch[1]);
                    $class = trim($classMatch[1]);

                    $fullClass = $namespace . '\\' . $class;

                    if (is_subclass_of($fullClass, \Illuminate\Database\Eloquent\Model::class)) {
                        $models[] = $fullClass;
                    }
                }
            }
        }

        return $models;
    }

    protected function generateTemporaryQueryScripts($phpFiles, $getAllModelClasses): void
    {
        $outputPath = storage_path('tmp_sql_logs');
        File::ensureDirectoryExists($outputPath);

        // Gather all model base names for regex
        $allModelNames = collect($getAllModelClasses)->mapWithKeys(function ($fqcn) {
            return [class_basename($fqcn) => $fqcn];
        })->keys()->implode('|');

        $counter = 1;
        $sqlCollection = [];
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getRealPath());

            // Kod içinde sorgu bloğu var mı kontrol et
            if (!Str::contains($content, ['with(', 'where(', 'whereHas(', 'join('])) {
                continue;
            }

            // Model bazlı sorguyu yakala (sadece bilinen Model:: ile başlayan bloklar)
            if (!preg_match("/((?:\\\$\\w+\\s*=\\s*)?(?:$allModelNames)::.*?->(?:with|where|whereHas|join)[^;]*;)/s", $content, $matches)) {
                continue;
            }

            $matchedCode = $matches[1];

            // Gelişmiş where vs. filtrelemesi: tüm array içindeki değişkenleri 'sekizlipenguen' ile değiştir
            $safeCode = preg_replace_callback('/->(where(?:In)?(?:Has)?|join)\s*\((.*?)\)/s', function ($match) {
                $method = $match[1];
                $params = $match[2];

                $newParams = preg_replace_callback('/(?<![\'"])\$[a-zA-Z_][a-zA-Z0-9_]*(\s*->\s*[a-zA-Z0-9_]+)*/', function () {
                    return "'sekizlipenguen'";
                }, $params);
                return "->{$method}({$newParams})";
            }, $matchedCode);
            // Model adını yakala (ör: CouponHistory::)
            preg_match('/([\\w\\\\]+)::/', $matchedCode, $modelMatch);
            $modelClass = $modelMatch[1] ?? null;

            $modelPaths = config('index-analyzer.model_paths', ['app/Models']);

            // 🔍 Model eşleşmeleri için tam sınıf isimlerini normalize et
            $normalizedModelClasses = collect($getAllModelClasses)->mapWithKeys(function ($fqcn) {
                $parts = explode('\\', $fqcn);
                return [end($parts) => $fqcn];
            });

            //dd($modelClass);

            if ($modelClass && isset($normalizedModelClasses[$modelClass])) {
                $importLine = 'use ' . $normalizedModelClasses[$modelClass] . ';';
            } elseif ($modelClass && !Str::contains($modelClass, '\\')) {
                foreach ($modelPaths as $path) {
                    $fullPath = base_path(trim($path, '/'));
                    $modelFiles = File::allFiles($fullPath);

                    foreach ($modelFiles as $modelFile) {
                        $relativePath = str_replace($fullPath . DIRECTORY_SEPARATOR, '', $modelFile->getRealPath());
                        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);

                        if (Str::endsWith($className, $modelClass)) {
                            $namespace = str_replace('/', '\\', trim($path, '/'));
                            $importLine = "use {$namespace}\\{$className};";
                            break 2;
                        }
                    }
                }

                if (!$importLine) {
                    continue;
                }
            }

            // Final script
            $script = "<?php\n\nuse Illuminate\\Support\\Facades\\DB;\n{$importLine}\nDB::enableQueryLog();\n\n// 💡 Otomatik tespit edilen sorgu bloğu:\n\n{$safeCode}\n\nreturn DB::getQueryLog();\n";

            $filename = $outputPath . '/query_' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '.php';
            File::put($filename, $script);

            // Run the temp file and collect the SQL queries
            try {
                $result = include $filename;
                if (is_array($result)) {
                    $sqlCollection = array_merge($sqlCollection, $result);
                }
            } catch (\Throwable $e) {
                // Optionally log or ignore
            }
            $counter++;
        }

        // Write all collected SQL queries to a log file
        $logFile = storage_path('tmp_sql_logs/all_queries.log');
        $logContent = '';
        foreach ($sqlCollection as $query) {
            if (is_array($query) && isset($query['query'])) {
                $logContent .= $query['query'] . "\n";
            } elseif (is_string($query)) {
                $logContent .= $query . "\n";
            }
        }
        File::put($logFile, $logContent);
    }
}
