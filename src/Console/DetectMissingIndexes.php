<?php

namespace SekizliPenguen\IndexAnalyzer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DetectMissingIndexes extends Command
{
    protected $signature = 'optimize:index {--execute : Automatically apply missing indexes} {--sql= : Export SQL to file}';
    protected $description = 'Scan queries and suggest or create missing indexes';
    protected string $currentFilePath = '';

    public function handle(): void
    {
        $this->info('🔍 Starting index analysis...');
        if (!config('index-analyzer.enabled')) {
            $this->info('Index Analyzer is disabled in configuration.');
            return;
        }
        $configPath = config('index-analyzer.scan_path') === 'base_path'
            ? base_path()
            : base_path(config('index-analyzer.scan_path'));

        $excludedDirs = config('index-analyzer.exclude', []);

        $phpFiles = collect(File::allFiles($configPath))
            ->reject(fn($file) => collect($excludedDirs)->contains(fn($dir) => str_contains($file->getPath(), $dir)))
            ->all();
        $indexSuggestions = [];
        $successCount = 0;
        $existingIndexes = [];

        foreach ($phpFiles as $file) {
            if ($file->getExtension() !== 'php') continue;
            $this->currentFilePath = $file->getRealPath();
            $code = file_get_contents($file->getRealPath());
            $columnsPerTable = $this->extractColumnsGroupedByTable($code);
            foreach ($columnsPerTable as $table => $columns) {
                foreach ($columns as $column) {
                    if (!Schema::hasTable($table)) continue;
                    if (!Schema::hasColumn($table, $column)) {
                        $this->warn("⚠️  Skipped: Column '{$column}' does not exist on table '{$table}' (file: " . basename($this->currentFilePath) . ")");
                        continue;
                    }

                    //$this->line("DEBUG: Checking index on {$table}.{$column}");
                    $key = "{$table}.{$column}";
                    if (!in_array($key, $existingIndexes) && !array_key_exists($key, $indexSuggestions)) {
                        if (!$this->hasIndex($table, $column)) {
                            $sql = "CREATE INDEX idx_{$table}_{$column} ON `{$table}`(`{$column}`);";
                            $indexSuggestions[$key] = $sql;
                            $this->warn("Missing index on {$table}.{$column}");
                            $this->line("Suggested: {$sql}");
                        } else {
                            if (!in_array($key, $existingIndexes)) {
                                $existingIndexes[] = $key;
                            }
                            $this->info("✅ Index exists on {$table}.{$column} (file: " . basename($this->currentFilePath) . ")");
                            $successCount++;
                        }
                    }
                }
            }
        }

        if ($this->option('sql')) {
            $file = $this->option('sql');
            File::put($file, implode("\n", array_values($indexSuggestions)));
            $this->info("SQL saved to {$file}");
        }

        if ($this->option('execute')) {
            foreach ($indexSuggestions as $query) {
                DB::statement($query);
                $this->info("Executed: {$query}");
            }
        }

        $this->info("✅ Total existing indexes confirmed: {$successCount}");
        if (count($existingIndexes) > 0) {
            $this->info("Existing indexes found on columns:");
            foreach ($existingIndexes as $indexedColumn) {
                $this->line(" - {$indexedColumn}");
            }
        }
    }

    protected function extractColumnsGroupedByTable(string $code): array
    {
        $columns = [];
        $tables = [];

        // Kapsamı genişlet: Artık sadece ::where değil, tüm :: statik çağrıları için modeli tespit et
        if (preg_match_all('/([A-Z][a-zA-Z0-9_\\\\]+)::/', $code, $modelMatches)) {
            foreach ($modelMatches[1] as $modelClass) {
                $tableName = $this->resolveModelClass($modelClass);
                if ($tableName) {
                    $tables[] = $tableName;
                }
            }
        }

        // 2. where gibi ifadelerden kolonları yakala
        $pattern = '/->(?:where|join|orWhere|whereHas)\s*\(\s*(\[.*?\]|[\'\\"](\w+)[\'\\"])/s';
        preg_match_all($pattern, $code, $matches);
        $columnList = array_unique($matches[2]);

        // 3. Tablolara kolonları eşle
        $result = [];
        foreach ($tables as $table) {
            $result[$table] = $columnList;
        }

        return $result;
    }

    protected function resolveModelClass(string $shortName): ?string
    {
        // Model ismini doğrudan tablo ismine çevirir ve veritabanında olup olmadığını kontrol eder.
        $schema = DB::getDatabaseName();
        $tableName = Str::snake(Str::pluralStudly(class_basename($shortName)));

        try {
            $exists = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_name', $tableName)
                ->exists();

            return $exists ? $tableName : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function hasIndex(string $table, string $column): bool
    {
        $schema = DB::getDatabaseName();
        $check = DB::selectOne("SELECT COUNT(1) as cnt FROM information_schema.STATISTICS WHERE table_schema = ? AND table_name = ? AND column_name = ?", [
            $schema, $table, $column
        ]);
        return (int)$check->cnt > 0;
    }
}
