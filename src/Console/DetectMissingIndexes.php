<?php

namespace SekizliPenguen\IndexAnalyzer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use SekizliPenguen\IndexAnalyzer\Helpers\FileValidator;
use SekizliPenguen\IndexAnalyzer\Helpers\ImportAnalyzer;
use SekizliPenguen\IndexAnalyzer\Helpers\ModelFinder;
use SekizliPenguen\IndexAnalyzer\Helpers\QuerySanitizer;
use Throwable;

class DetectMissingIndexes extends Command
{
    protected $signature = 'optimize:index
                            {--execute : Otomatik olarak eksik indeksleri uygula}
                            {--sql= : Ekran yerine dosyaya SQL çıktısı ver}
                            {--dry-run : Sadece simülasyon yap, hiçbir şey çalıştırma veya kaydetme}
                            {--model= : Sadece belirli bir modeli eşleştiren sorguları tara}
                            {--impact-analysis : İndeks önerileri için etki analizi yap}
                            {--composite : Kompozit indeks önerileri oluştur}
                            {--limit=1000 : İşlenecek maksimum sorgu sayısı (varsayılan: 1000)}
                            {--cardinality-threshold=25 : Kardinalite analizi için eşik değeri (varsayılan: 25)}';
    protected $description = 'Scan queries and suggest or create missing indexes';

    public function handle(): void
    {
        $this->info('🔍 Laravel Index Analyzer - AST tabanlı sorgu analizi başlatılıyor...');

        if (!config('index-analyzer.enabled')) {
            $this->info('Index Analyzer yapılandırma tarafından devre dışı bırakılmış.');
            return;
        }

        // Projedeki tüm modelleri ve trait'leri önbelleğe al
        $this->info('🔎 Proje içindeki model ve trait\'leri tespit ediyor...');
        ModelFinder::cacheAllModels();
        ModelFinder::cacheAllTraits();

        // Başlangıç zamanını kaydet
        $startTime = microtime(true);

        $isDryRun = $this->option('dry-run');
        $onlyModel = $this->option('model');
        $exportSqlPath = $this->option('sql');
        $shouldExecute = $this->option('execute');

        // MySQL veritabanı bağlantısı kontrol et
        if (config('database.default') !== 'mysql' && !$isDryRun) {
            $this->warn('⚠️ Bu araç şu anda sadece MySQL veritabanlarını desteklemektedir.');
            if (!$this->confirm('Devam etmek istiyor musunuz?', false)) {
                return;
            }
        }

        $configPath = config('index-analyzer.scan_path') === 'base_path'
            ? base_path()
            : base_path(trim(config('index-analyzer.scan_path'), '/'));

        $excludedDirs = config('index-analyzer.exclude', []);

        $this->info('📂 Proje dosyaları taranıyor...');

        $phpFiles = collect(File::allFiles($configPath))
            ->reject(function ($file) use ($excludedDirs) {
                foreach ($excludedDirs as $dir) {
                    if (str_contains($file->getPath(), $dir)) {
                        return true;
                    }
                }
                return false;
            })
            ->filter(fn($file) => $file->getExtension() === 'php')
            ->values();

        $this->info("📝 Toplam {$phpFiles->count()} PHP dosyası bulundu, sorguları çıkarma işlemi başlatılıyor...");

        $querySources = [];
        $queries = $this->extractQueryChains($phpFiles, $querySources);

        // Sorgu sayısını sınırla
        $queryLimit = (int)$this->option('limit') ?: 1000; // Varsayılan değer ekle
        if (count($queries) > $queryLimit && $queryLimit > 0) {
            $this->warn("⚠️ {$queryLimit} limit nedeniyle sadece ilk {$queryLimit} sorgu işlenecek.");
            $queries = array_slice($queries, 0, $queryLimit);
        }

        if ($onlyModel) {
            $this->info("🔍 Sadece '{$onlyModel}' ile ilgili sorgular filtreleniyor...");
            $queries = array_filter($queries, fn($query) => str_contains($query, $onlyModel));
        }

        if ($isDryRun) {
            $this->info('🔬 Dry run modu: sorgular sadece simüle edildi.');
            if ($this->option('verbose')) {
                foreach ($queries as $query) {
                    $this->line($query);
                }
            } else {
                $this->info("📊 Toplam " . count($queries) . " sorgu tespit edildi. Ayrıntıları görmek için --verbose seçeneğini kullanın.");
            }
            return;
        }

        $outputPath = storage_path('tmp_sql_logs');
        // Temizleme işlemi: klasörü ve önceki log dosyalarını sil
        if (File::exists($outputPath)) {
            File::cleanDirectory($outputPath);
        } else {
            File::makeDirectory($outputPath, 0755, true);
        }

        File::put($outputPath . '/all_queries.log', '');
        File::put($outputPath . '/query_analysis.log', '');

        $this->info('🧪 Sorguları simüle ediyor ve SQL komutlarını çıkarıyor...');
        $this->output->progressStart(count($queries));

        $counter = 1;
        $successCount = 0;
        $errorCount = 0;
        $uniqueQueries = [];

        foreach ($queries as $index => $query) {
            $filename = $outputPath . '/query_' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '_' . substr(md5($query), 0, 6) . '.php';

            // Temel importları daima ekle
            $imports = ['Illuminate\\Support\\Facades\\DB'];

            // Kaynak dosyadan importları analiz et
            if (isset($querySources[$index])) {
                $sourceFile = $querySources[$index];
                if (file_exists($sourceFile)) {
                    $analysis = ImportAnalyzer::analyzeFile($sourceFile);

                    // Namespace'e dayalı importları ekle
                    foreach ($analysis['imports'] as $fullNamespace) {
                        $imports[] = $fullNamespace;
                    }

                    // Sorgu içindeki sınıf ve trait kullanımlarını analiz et
                    $usages = ImportAnalyzer::extractClassAndTraitUsages($query);

                    // Sorgu içindeki trait'leri ekle
                    foreach ($usages['traits'] as $trait) {
                        // Trait tam yolunu bulmaya çalış
                        if (!str_contains($trait, '\\') && isset($analysis['imports'][$trait])) {
                            $imports[] = $analysis['imports'][$trait];
                        } elseif (!str_contains($trait, '\\') && $analysis['namespace']) {
                            // Namespace içinde olabilir
                            $imports[] = $analysis['namespace'] . '\\' . $trait;
                        } else {
                            $imports[] = $trait;
                        }
                    }

                    // Sorgu içindeki sınıfları ekle
                    foreach ($usages['classes'] as $class) {
                        if (!str_contains($class, '\\') && isset($analysis['imports'][$class])) {
                            $imports[] = $analysis['imports'][$class];
                        } elseif (str_contains($class, '\\')) {
                            $imports[] = $class;
                        }
                    }
                }
            }

            // Trait kullanımlarını tespit et - hem normal hem anonim sınıflardaki trait'ler
            preg_match_all('/use\s+([\w\\]+)(?:\s*;|\s+(?:in))/i', $query, $traitMatches);
            preg_match_all('/\(new\s+class[^{]*{[^}]*use\s+([\w\\]+);/is', $query, $anonTraitMatches);

            // Normal trait kullanımları
            if (!empty($traitMatches[1])) {
                foreach ($traitMatches[1] as $trait) {
                    $imports[] = $trait;
                }
            }

            // Anonim sınıf içinde trait kullanımları
            if (!empty($anonTraitMatches[1])) {
                foreach ($anonTraitMatches[1] as $trait) {
                    $imports[] = $trait;
                }
            }

            // Namespace'leri tespit et
            preg_match_all('/new\s+([\\\w]+)/i', $query, $classMatches);
            if (!empty($classMatches[1])) {
                foreach ($classMatches[1] as $class) {
                    if (str_contains($class, '\\')) {
                        $imports[] = $class;
                    }
                }
            }

            // StaticCall sınıflarını tespit et (\Sınıf::metod() şeklinde olanlar)
            preg_match_all('/([\\\w]+)::/i', $query, $staticMatches);
            if (!empty($staticMatches[1])) {
                foreach ($staticMatches[1] as $static) {
                    if ($static !== 'self' && $static !== 'static' && $static !== 'parent' && !str_starts_with($static, '$')) {
                        $imports[] = $static;
                    }
                }
            }

            // Import satırlarını oluştur
            $importLines = [];

            // Modeller ve trait'ler için otomatik import
            $autoImports = ModelFinder::getAllImports();

            // İki import listesini birleştir
            if (is_array($imports)) {
                foreach (array_unique($imports) as $import) {
                    if (is_string($import) && trim($import) !== '') {
                        $importLines[] = "use {$import};";
                    }
                }
            }

            // Otomatik importları ekle
            foreach ($autoImports as $import) {
                if (!in_array($import, $importLines)) {
                    $importLines[] = $import;
                }
            }

            // Aynı isimli sınıf ve trait'lerin çakışmasını önlemek için alias ekle
            $importLines = QuerySanitizer::processImportsWithAlias($importLines);

            // Sorgu öncesi genel hata çözücüler
            $fixerCode = "";

            // Yaygın sınıf ve trait'ler için stub oluştur
            if (preg_match_all('/::class|new\s+([\w\\]+)|([\w\\]+)::|use\s+([\w\\]+)(?:\s*;|\s+(?:in))/i', $query, $classMatches)) {
                $allClasses = array_merge(
                    $classMatches[1] ?? [],
                    $classMatches[2] ?? [],
                    $classMatches[3] ?? []
                );

                foreach ($allClasses as $className) {
                    if (empty($className)) continue;

                    $className = trim($className);
                    if (in_array($className, ['self', 'static', 'parent']) || str_starts_with($className, '$')) {
                        continue;
                    }

                    // Sınıf için stub oluştur
                    if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                        // Model stub'ı oluştur
                        $fixerCode .= "if (!class_exists('{$className}')) { class {$className} extends \Illuminate\Database\Eloquent\Model {} }\n";
                    }
                }
            }

            // Sorgu dosyasını oluştur
            $script = "<?php\n\n";

            // Hata çözücü kodunu ekle (eğer varsa)
            if (!empty($fixerCode)) {
                $script .= "// Sınıf ve trait çözücüleri\n{$fixerCode}\n";
            }

            // İmport satırlarını ekle
            if (!empty($importLines)) {
                $script .= implode("\n", $importLines);
            } else {
                // En azından DB facade'ini ekle
                $script .= "use Illuminate\\Support\\Facades\\DB;";
            }

            // Sorguları temizle ve güvenli hale getir
            $safeQuery = QuerySanitizer::sanitize($query);

            // Çalıştırılabilir sorgu oluştur - anonim sınıf içeren sorguları özel işleme
            $executableQuery = $this->createExecutableQuery($safeQuery, $imports);

            $script .= "\n\n// 💡 Otomatik tespit edilen sorgu bloğu:\n\nreturn DB::pretend(function () {\n    {$executableQuery}\n});\n";
            File::put($filename, $script);
            // Immediately execute and collect SQL queries
            try {
                // Sorgu dosyasını kontrol et
                if (!file_exists($filename)) {
                    throw new \Exception("Sorgu dosyası oluşturulamadı: {$filename}");
                }

                // Yedek plan: Eğer anonim sınıf trait'i içeriyorsa ve hata alınabilecekse, önceden kaydedelim
                if (strpos($query, 'new class') !== false && strpos($query, 'use ') !== false) {
                    // Yedek dosyayı kaydet
                    File::put($filename . '.original', $script);
                }

                // Dosyayı güvenli şekilde dahil et
                $result = @include $filename;
                if (is_array($result)) {
                    $content = '';
                    foreach ($result as $entry) {
                        if (isset($entry['query'])) {
                            $sql = $entry['query'];
                            // Aynı sorguyu tekrar etmemek için kontrol
                            $hash = md5($sql);
                            if (!isset($uniqueQueries[$hash])) {
                                $uniqueQueries[$hash] = true;
                                $content .= $sql . "\n";

                                // Ayrıntılı çıktı isteniyorsa sorgu içeriğini göster
                                if ($this->option('verbose')) {
                                    $this->line("SQL: " . Str::limit($sql, 100));
                                }
                            }
                        }
                    }

                    if (!empty($content)) {
                        File::append(storage_path('tmp_sql_logs/all_queries.log'), $content);
                        $successCount++;
                    }
                }
            } catch (Throwable $e) {
                // Hata durumunda
                $errorCount++;

                // Trait veya class bulunamadı hataları için özel işlem
                if (str_contains($e->getMessage(), 'Trait') || str_contains($e->getMessage(), 'Class')) {
                    // Sorgu içinde geçen sınıf ve trait'leri çıkaralım
                    preg_match_all('/use\s+([\w\\]+);/i', $query, $traitMatches);
                    preg_match_all('/new\s+([\w\\]+)/i', $query, $classMatches);
                    preg_match_all('/([\w\\]+)::/i', $query, $staticMatches);

                    // Hata mesajını anlamlandır
                    $missingEntity = null;
                    if (preg_match('/Trait "([^"]+)" not found/', $e->getMessage(), $entityMatches)) {
                        $missingEntity = $entityMatches[1];
                        // Bulunamayan trait'i öneri listesine ekle
                        ModelFinder::addTraitMapping($missingEntity, "App\\Traits\\{$missingEntity}");
                    } elseif (preg_match('/Class "([^"]+)" not found/', $e->getMessage(), $entityMatches)) {
                        $missingEntity = $entityMatches[1];
                        // Bulunamayan sınıfı öneri listesine ekle
                        ModelFinder::addModelMapping($missingEntity, "App\\Models\\{$missingEntity}");
                    }

                    // Otomatik model sınıfları oluştur
                    $stubScript = $script;

                    if ($missingEntity) {
                        if (str_contains($e->getMessage(), 'Trait')) {
                            // Eksik trait için stub ekle
                            $stubScript = "<?php\n\n// Eksik trait için stub\ntrait {$missingEntity} { function productModel() { return new \Illuminate\Database\Eloquent\Builder(new \Illuminate\Database\Eloquent\Model); } }\n\n" . $stubScript;
                        } else {
                            // Eksik sınıf için stub ekle
                            $stubScript = "<?php\n\n// Eksik sınıf için stub\nclass {$missingEntity} extends \Illuminate\Database\Eloquent\Model {}\n\n" . $stubScript;
                        }

                        // Otomatik oluşturulan stub ile yeniden dene
                        File::put($filename . '.stub', $stubScript);

                        try {
                            // Stub dosyasını çalıştır
                            $stubResult = @include $filename . '.stub';
                            if (is_array($stubResult)) {
                                // Eğer başarılı olursa, bu sonuçları kullan
                                $result = $stubResult;
                            }
                        } catch (\Throwable $stubError) {
                            // Stub çalıştırma başarısız oldu, devam et
                        }
                    }

                    // Sorguyu atla, ama dosyayı kaydet
                    $loggedQuery = str_replace($query, "// DEVRE DIŞI: {$e->getMessage()}\n// {$query}", $script);
                    File::put($filename . '.disabled', $loggedQuery);

                    // Verbose modundaysa detayları göster
                    if ($this->option('verbose') || true) { // Geçici olarak tüm hataları göster
                        $this->warn("⚠️ Sorgu simülasyonu hatası (" . basename($filename) . "): " . $e->getMessage());
                        $this->warn("   💡 İpucu: Bu sorgu için gerekli bir trait veya sınıf bulunamadı: " . ($missingEntity ?? 'Bilinmiyor'));
                        $this->warn("   Sorgu: " . Str::limit($query, 100));

                        // Tespit edilen trait'leri göster
                        if (!empty($traitMatches[1])) {
                            $this->warn("   Tespit edilen trait kullanımları: " . implode(", ", $traitMatches[1]));
                        }

                        // Tespit edilen sınıfları göster
                        $allClasses = array_merge(
                            $classMatches[1] ?? [],
                            $staticMatches[1] ?? []
                        );
                        if (!empty($allClasses)) {
                            $this->warn("   Tespit edilen sınıf kullanımları: " . implode(", ", $allClasses));
                        }
                    }
                } else {
                    // Diğer hatalar için standart log
                    if ($this->option('verbose')) {
                        $this->warn("⚠️ Sorgu simülasyonu hatası (" . basename($filename) . "): " . $e->getMessage());
                    }
                }
            }
            $counter++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info("✅ {$successCount} sorgu başarıyla simüle edildi, {$errorCount} hata alındı.");

        $this->info("📊 Toplam analiz edilen sorgu sayısı: " . count($queries));

        // Index analysis based on collected queries
        $this->info('🔍 Veritabanı indeks analizi başlatılıyor...');

        $logFile = storage_path('tmp_sql_logs/all_queries.log');

        if (!File::exists($logFile) || filesize($logFile) === 0) {
            $this->warn("⚠️ SQL log dosyası bulunamadı veya boş: {$logFile}");
            $this->info("💡 İpucu: Kodunuzdaki sorgular doğru şekilde tanımlanmış mı kontrol edin.");
            return;
        }

        $lines = explode("\n", File::get($logFile));
        $lines = array_filter($lines); // Boş satırları kaldır

        $this->info("🔢 " . count($lines) . " sorgu SQL olarak çevirildi ve analiz edilecek.");

        // Önbelleğe alınmış veritabanı tablo ve indeks bilgileri
        $this->info('📂 Veritabanı tablolarını listeleme...');
        $existingTables = collect(DB::select('SHOW TABLES'))->map(function ($table) {
            return collect((array)$table)->first();
        })->toArray();

        $this->info("📋 Toplam " . count($existingTables) . " tablo bulundu.");

        $existingIndexes = [];
        $missingIndexes = [];

        foreach ($lines as $sql) {
            if (!trim($sql)) continue;

            $logDetail = "-- SQL: {$sql}\n";

            preg_match_all('/(?:from|join|update|into|delete\s+from)\s+`?(\w+)`?/i', $sql, $tableMatches);
            $tables = array_unique($tableMatches[1] ?? []);

            foreach ($tables as $table) {
                $logDetail .= "📁 Tablo: {$table}\n";
                if (!in_array($table, $existingTables)) {
                    $logDetail .= "⚠️ Tablo mevcut değil: {$table}\n";
                    File::append(storage_path('tmp_sql_logs/query_analysis.log'), $logDetail . "\n");
                    continue;
                }

                if (!isset($existingIndexes[$table])) {
                    $indexes = DB::select("SHOW INDEX FROM `{$table}`");
                    $existingIndexes[$table] = collect($indexes)->pluck('Column_name')->unique()->toArray();
                }

                // Hem table.column hem sadece column yapısını yakala
                preg_match_all('/(?:where|on|having|order by)[^;]*?`?(\w+)`\.`?(\w+)`?/i', $sql, $matchesWithTable);
                preg_match_all('/(?:where|on|having|order by)[^;]*?`?(\w+)`?/i', $sql, $matchesWithoutTable);

                $columns = [];

                // table.column formatındaki eşleşmeleri al
                foreach ($matchesWithTable[1] as $index => $tableName) {
                    $columns[] = "{$tableName}.{$matchesWithTable[2][$index]}";
                }

                // Sadece column ismine sahip olanları ekle
                foreach ($matchesWithoutTable[1] as $col) {
                    if (!str_contains($col, '.')) {
                        $columns[] = $col;
                    }
                }

                $columns = array_unique($columns);
                foreach ($columns as $col) {
                    $colName = str_contains($col, '.') ? explode('.', $col)[1] : $col;
                    // Kolon adı boşsa atla
                    if (!$colName) continue;
                    // Kolon adı tablo adıyla aynıysa veya tablo içinde yoksa atla
                    if ($colName === $table || !Schema::hasColumn($table, $colName)) {
                        continue;
                    }
                    // PRIMARY KEY kontrolü
                    $indexCols = collect(DB::select("SHOW INDEX FROM `{$table}`"))->groupBy('Key_name');
                    $primaryCols = $indexCols->get('PRIMARY', collect())->pluck('Column_name')->toArray();
                    if (in_array($colName, $primaryCols)) {
                        $logDetail .= "🔒 PRIMARY KEY mevcut: {$table}.{$colName}\n";
                        continue;
                    }
                    $logDetail .= "🔎 Kolon: {$col} ";
                    $indexSql = "ALTER TABLE `{$table}` ADD INDEX (`{$colName}`);";
                    if (in_array($colName, $existingIndexes[$table])) {
                        $this->line("✅ Zaten index var: {$table}.{$colName}");
                        $logDetail .= "✅ Zaten index var: {$table}.{$colName}\n";
                    } else {
                        $logDetail .= "🛠️ Önerilen index: {$table}.{$colName}\n";
                        $missingIndexes["{$table}.{$colName}"] = [
                            'table' => $table,
                            'column' => $colName,
                            'sql' => $indexSql,
                        ];
                    }
                }
            }
            File::append(storage_path('tmp_sql_logs/query_analysis.log'), $logDetail . "\n");
        }

        // Öneri sıralamasını kullanım sıklığına göre yap
        $missingIndexes = collect($missingIndexes)
            ->unique('sql')
            ->sortByDesc('frequency')
            ->values()
            ->all();

        if (empty($missingIndexes)) {
            $this->info('✅ Tüm sorgular zaten uygun indekslere sahip.');
            return;
        }

        // Etki analizi yap
        $impactAnalysis = $this->option('impact-analysis');
        if ($impactAnalysis) {
            $this->performImpactAnalysis($missingIndexes);
        }

        // Kompozit indeks önerileri oluştur
        $shouldCreateComposite = $this->option('composite');
        $compositeIndexes = [];
        if ($shouldCreateComposite) {
            $compositeIndexes = $this->generateCompositeIndexes($missingIndexes);
            $missingIndexes = array_merge($missingIndexes, $compositeIndexes);
        }

        // Önerileri tabloda göster
        $this->line('\n🔧 Önerilen indeksler:');
        $headers = ['Tablo', 'Sütun', 'Kullanım', 'Tür', 'SQL'];
        $rows = [];

        foreach (array_values($missingIndexes) as $index) {
            $indexType = $index['unique'] ?? false ? 'UNIQUE' : 'NORMAL';
            $isComposite = isset($index['composite']) && $index['composite'];
            if ($isComposite) {
                $indexType = 'KOMPOZİT';
            }

            $rows[] = [
                $index['table'],
                isset($index['columns']) ? implode(', ', $index['columns']) : $index['column'],
                $index['frequency'] ?? 'N/A',
                $indexType,
                $index['sql']
            ];
        }

        $this->table($headers, $rows);

        // İndeksleri uygula veya dışa aktar
        if ($shouldExecute) {
            if ($this->confirm('Önerilen indeksler eklensin mi?', true)) {
                $bar = $this->output->createProgressBar(count($missingIndexes));
                $bar->start();
                $errorCount = 0;

                foreach (array_values($missingIndexes) as $index) {
                    try {
                        \DB::statement($index['sql']);
                        $bar->advance();
                    } catch (Throwable $e) {
                        $errorCount++;
                        $this->newLine();
                        $this->warn("İndeks eklenemedi: {$index['sql']}");
                        $this->warn("Hata: {$e->getMessage()}");
                    }
                }

                $bar->finish();
                $this->newLine(2);
                $this->info('✅ İndeks ekleme işlemi tamamlandı. ' .
                    ($errorCount > 0 ? "({$errorCount} hata)" : ''));
            }
        } elseif ($exportSqlPath) {
            $sqlOnlyAlters = array_unique(array_filter(array_column($missingIndexes, 'sql'),
                fn($line) => Str::startsWith(trim($line), 'ALTER TABLE')));

            // SQL dosyasının başına yorum ekle
            $sqlContent = "-- İndeks Analizi Sonuçları\n";
            $sqlContent .= "-- Oluşturulma Tarihi: " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- Toplam Önerilen İndeks: " . count($sqlOnlyAlters) . "\n\n";
            $sqlContent .= implode("\n", $sqlOnlyAlters) . "\n";

            File::put($exportSqlPath, $sqlContent);
            $this->info("SQL çıktısı '{$exportSqlPath}' dosyasına yazıldı.");
        }

        // Analiz sonuçlarının özeti
        $this->line('\n📊 Analiz Özeti:');
        $this->line('👉 Toplam Analiz Edilen Sorgu: ' . count($queries));
        $this->line('👉 Önerilen Toplam İndeks: ' . count($missingIndexes));
        if ($shouldCreateComposite) {
            $this->line('👉 Önerilen Kompozit İndeks: ' . count($compositeIndexes));
        }
    }

    /**
     * İndeks önerileri için etki analizi yapar
     *
     * @param array $indexes Önerilen indeksler
     * @return void
     */
    protected function performImpactAnalysis(array $indexes): void
    {
        $this->info('\n🔬 İndeks Etki Analizi Yapılıyor...');

        $headers = ['Tablo', 'Sütun', 'Tablo Boyutu', 'Toplam Satır', 'Benzersiz Değer', 'Kardinalite', 'Tavsiye'];
        $rows = [];

        $cardinalityThreshold = (int)$this->option('cardinality-threshold') ?: 25;

        foreach ($indexes as $index) {
            $table = $index['table'];
            $column = $index['column'];

            try {
                // Tablo boyutunu al
                $tableSizeInfo = DB::select("SELECT 
                    table_name AS 'table',
                    round(((data_length + index_length) / 1024 / 1024), 2) 'size_in_mb'
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE()
                    AND table_name = '{$table}'");

                $tableSize = count($tableSizeInfo) > 0 ? $tableSizeInfo[0]->size_in_mb . ' MB' : 'N/A';

                // Toplam satır
                $totalRows = DB::select("SELECT COUNT(*) as total FROM `{$table}`")[0]->total;

                // Benzersiz değer sayısı
                $uniqueCount = DB::select("SELECT COUNT(DISTINCT `{$column}`) as unique_count FROM `{$table}`")[0]->unique_count;

                // Kardinalite (benzersiz değer oranı)
                $cardinality = $totalRows > 0 ? round(($uniqueCount / $totalRows) * 100, 2) : 0;

                // Tavsiye
                $recommendation = 'NORMAL INDEX';
                if ($cardinality > 90) {
                    $recommendation = 'UNIQUE INDEX ✓';
                } elseif ($cardinality < $cardinalityThreshold) {
                    $recommendation = 'DÜŞÜK KARDİNALİTE ⚠️';
                }

                $rows[] = [
                    $table,
                    $column,
                    $tableSize,
                    number_format($totalRows),
                    number_format($uniqueCount),
                    '%' . $cardinality,
                    $recommendation
                ];

            } catch (Throwable $e) {
                $rows[] = [$table, $column, 'N/A', 'N/A', 'N/A', 'N/A', 'ANALİZ HATASI'];
            }
        }

        $this->table($headers, $rows);

        $this->info('ℹ️ Kardinalite: Bir sütundaki benzersiz değerlerin toplam satır sayısına oranı.');
        $this->info("ℹ️ Düşük kardinalite ({$cardinalityThreshold}% altı): Indeks performansı düşük olabilir.");
    }

    /**
     * Kompozit indeks önerileri oluşturur
     *
     * @param array $singleIndexes Tekli indeks önerileri
     * @return array
     */
    protected function generateCompositeIndexes(array $singleIndexes): array
    {
        $this->info('\n🧩 Kompozit İndeks Analizi Yapılıyor...');

        $tableGroups = [];
        $compositeIndexes = [];

        // Tabloları grupla
        foreach ($singleIndexes as $index) {
            $table = $index['table'];
            if (!isset($tableGroups[$table])) {
                $tableGroups[$table] = [];
            }
            $tableGroups[$table][] = $index;
        }

        // Her tablo için olası kompozit indeksleri incele
        foreach ($tableGroups as $table => $indexes) {
            // Sadece sık kullanılan (frequency değeri 2 ve üzeri olan) sütunları ele al
            $frequentColumns = array_filter($indexes, fn($idx) => isset($idx['frequency']) && $idx['frequency'] >= 2);

            // Aynı tabloda sık kullanılan 2 veya daha fazla sütun varsa
            if (count($frequentColumns) >= 2) {
                // Kullanım sıklığına göre sırala
                usort($frequentColumns, function ($a, $b) {
                    return ($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0);
                });

                // En çok kullanılan 3 sütunu al (çok büyük kompozit indekslerden kaçın)
                $topColumns = array_slice($frequentColumns, 0, 3);

                $columnNames = array_column($topColumns, 'column');
                $columnStr = implode('`, `', $columnNames);

                $compositeKey = "{$table}_" . implode('_', $columnNames);
                $compositeSQL = "ALTER TABLE `{$table}` ADD INDEX `idx_{$compositeKey}` (`{$columnStr}`);";

                $compositeIndexes[$compositeKey] = [
                    'table' => $table,
                    'columns' => $columnNames,
                    'sql' => $compositeSQL,
                    'frequency' => array_sum(array_column($topColumns, 'frequency')),
                    'composite' => true
                ];
            }
        }

        if (empty($compositeIndexes)) {
            $this->info('Kompozit indeks oluşturmak için yeterli ilişkili sütun bulunamadı.');
        } else {
            $this->info(count($compositeIndexes) . ' kompozit indeks önerisi oluşturuldu.');
        }

        return array_values($compositeIndexes);
    }

    /**
     * Sorgu içindeki use ifadelerini ve import gereksinimlerini analiz eder
     *
     * @param string $content Dosya içeriği
     * @return array Tespit edilen importlar
     */
    protected function analyzeUseStatements(string $content): array
    {
        $imports = [];

        // Namespace'leri bul
        preg_match('/namespace\s+([^;]+);/i', $content, $nsMatch);
        $namespace = $nsMatch[1] ?? null;

        // use ifadelerini bul - hem normal importları hem trait kullanımlarını destekle
        preg_match_all('/^use\s+([^;{]+);/m', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                // as ifadeleri
                if (str_contains($match, ' as ')) {
                    list($class, $alias) = explode(' as ', $match);
                    $imports[$alias] = trim($class);
                } else {
                    $parts = explode('\\', $match);
                    $className = end($parts);
                    $imports[$className] = trim($match);
                }
            }
        }

        // Trait kullanımlarını bul (sınıf içinde)
        preg_match_all('/use\s+([^;{]+);/m', $content, $traitMatches);
        if (!empty($traitMatches[1])) {
            foreach ($traitMatches[1] as $trait) {
                $trait = trim($trait);
                // Eğer bu bir sınıf içindeki trait kullanımı ise
                if (!str_contains($trait, '\\')) {
                    // Namespace mevcut ise, namespace içindeki trait'i kontrol et
                    if ($namespace) {
                        $imports[$trait] = $namespace . '\\' . $trait;
                    } // Alternatif olarak, aynı isimde bir use statement var mı kontrol et
                    elseif (isset($imports[$trait])) {
                        // Zaten eklenmiş, bir şey yapma
                    } else {
                        // Tam yolu bulamadığımız için en azından adını ekleyelim
                        $imports[$trait] = $trait;
                    }
                }
            }
        }

        // Sınıf içi trait kullanımlarını ara
        preg_match_all('/class\s+[\w]+[^{]*{[^}]*use\s+([\w\\,\s]+);/is', $content, $classTraitMatches);
        if (!empty($classTraitMatches[1])) {
            foreach ($classTraitMatches[1] as $traitList) {
                $traits = array_map('trim', explode(',', $traitList));
                foreach ($traits as $trait) {
                    if (!str_contains($trait, '\\') && $namespace) {
                        $imports[$trait] = $namespace . '\\' . $trait;
                    } else {
                        $imports[$trait] = $trait;
                    }
                }
            }
        }

        return $imports;
    }

    /**
     * Belirli bir trait'in varlığını kontrol eden metod
     *
     * @param string $traitName Trait adı
     * @return bool Trait bulundu mu
     */
    protected function checkTraitExists(string $traitName): bool
    {
        // ModelFinder içinden kontrol et
        $fullTraitName = ModelFinder::getTraitClass($traitName);
        if ($fullTraitName && trait_exists($fullTraitName)) {
            return true;
        }

        // Base namespace'ler - yaygın Laravel ve kendi projeniz için
        $namespaces = [
            '',
            '\\',
            'App\\',
            'App\\Models\\',
            'App\\Services\\',
            'App\\Traits\\',
            'App\\Helpers\\',
            'Modules\\',
        ];

        // Her namespace'i dene
        foreach ($namespaces as $namespace) {
            $fullName = $namespace . $traitName;
            if (trait_exists($fullName)) {
                // Bulunan trait'i ModelFinder'a ekle
                ModelFinder::addTraitMapping($traitName, $fullName);
                return true;
            }
        }

        return false;
    }

    /**
     * Çalıştırılabilir sorgu oluştur - anonim sınıf özel işleme
     */
    protected function createExecutableQuery(string $query, array $imports): string
    {
        // Anonim sınıf kullanımı var mı?
        if (strpos($query, 'new class') !== false) {
            // Trait'leri çıkar
            preg_match_all('/(?:\()?new\s+class[^{]*{[^}]*use\s+([\w\\]+);/is', $query, $anonTraitMatches);

            // Eğer trait içeriyorsa, trait'i işleyelim
            if (!empty($anonTraitMatches[1])) {
                $modifiedQuery = $query;

                // Trait tanımlama kodu - eksik trait'ler için stub oluştur
                $traitStubs = "";

                foreach ($anonTraitMatches[1] as $trait) {
                    // ModelFinder'dan trait'i al
                    $fullTraitName = ModelFinder::getTraitClass($trait);

                    if (!$fullTraitName || !$this->checkTraitExists($fullTraitName)) {
                        // Trait bulunamadı, bir stub oluştur
                        $traitStubs .= "if (!trait_exists('{$trait}')) { trait {$trait} { function productModel() { return new \Illuminate\Database\Eloquent\Builder(new \Illuminate\Database\Eloquent\Model); } } }\n";
                    }
                }

                // Eğer stub oluşturulmuşsa, sorgunun başına ekle
                if (!empty($traitStubs)) {
                    $modifiedQuery = $traitStubs . $modifiedQuery;
                }

                return $modifiedQuery;
            }
        }

        return $query;
    }

    protected function extractQueryChains($phpFiles, &$querySources = []): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $queries = [];
        $querySources = []; // Sorguların kaynak dosyalarını takip et
        $pretty = new Standard();
        $modelPaths = config('index-analyzer.model_paths', ['app/Models']);

        // querySources değişkenini fonksiyon içinde referans olarak kullan
        $querySources = [];

        foreach ($phpFiles as $file) {
            try {
                $filePath = $file->getRealPath();

                // Dosya geçerli bir PHP dosyası mı kontrol et
                if (!FileValidator::isValidPhpFile($filePath)) {
                    continue;
                }

                // PHP syntax kontrolü (opsiyonel)
                $lintResult = FileValidator::lintPhpFile($filePath);
                if ($lintResult !== true) {
                    $this->warn("PHP Lint hatası: {$filePath} - {$lintResult}");
                    continue;
                }

                $code = file_get_contents($filePath);

                try {
                    $ast = $parser->parse($code);
                } catch (\Throwable $parseError) {
                    $this->warn("AST parse hatası: {$filePath} - {$parseError->getMessage()}");
                    continue;
                }

                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NameResolver());
                $traverser->addVisitor(new class($queries, $pretty, $modelPaths, $querySources) extends NodeVisitorAbstract {
                    public $queries;
                    public $pretty;
                    public $modelPaths;
                    public $querySources;
                    public $currentChain = [];
                    public $inQuery = false;

                    public function __construct(&$queries, $pretty, $modelPaths, &$querySources)
                    {
                        $this->queries = &$queries;
                        $this->pretty = $pretty;
                        $this->modelPaths = $modelPaths;
                        $this->querySources = &$querySources;
                    }

                    private function isModelClass($className): bool
                    {
                        if (Str::endsWith($className, 'Model') || Str::endsWith($className, 'Factory')) {
                            return true;
                        }

                        foreach ($this->modelPaths as $path) {
                            if (Str::contains($className, str_replace('/', '\\', $path))) {
                                return true;
                            }
                        }

                        return false;
                    }

                    public function enterNode(Node $node): void
                    {
                        // Model static çağrıları (User::where...)
                        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
                            $className = $node->class->toString();
                            if ($this->isModelClass($className)) {
                                $this->queries[] = $this->pretty->prettyPrint([$node]) . ";";
                            }
                        }

                        // Eloquent sorgu zincirleri (User::query()->where()->join()...)
                        if ($node instanceof Node\Expr\MethodCall) {
                            $methodName = $node->name->name ?? null;
                            if (in_array($methodName, ['where', 'whereIn', 'whereNotIn', 'whereNull', 'join', 'leftJoin', 'select', 'orderBy'])) {
                                // Var olan sorgu zincirini izliyoruz
                                if ($node->var instanceof Node\Expr\StaticCall && $node->var->class instanceof Node\Name) {
                                    $className = $node->var->class->toString();
                                    if ($this->isModelClass($className) && in_array($node->var->name->name, ['query', 'select', 'with'])) {
                                        $fullChain = $this->pretty->prettyPrint([$node]) . ";";
                                        $queryIndex = count($this->queries);
                                        $this->queries[] = $fullChain;
                                        // Kaynak dosyayı sakla
                                        $this->querySources[$queryIndex] = $file->getRealPath();
                                    }
                                } else if ($node->var instanceof Node\Expr\MethodCall) {
                                    // Daha derin bir sorgu zinciri olabilir
                                    $fullChain = $this->pretty->prettyPrint([$node]) . ";";
                                    $this->queries[] = $fullChain;
                                }
                            }
                        }

                        // DB facade kullanımları (DB::table()->where()->join()...)
                        if ($node instanceof Node\Expr\StaticCall &&
                            $node->class instanceof Node\Name &&
                            ($node->class->toString() === 'DB' || $node->class->toString() === 'Illuminate\\Support\\Facades\\DB') &&
                            $node->name->name === 'table') {
                            $fullChain = $this->pretty->prettyPrint([$node]) . ";";
                            $this->queries[] = $fullChain;
                        }
                    }
                });
                $traverser->traverse($ast);
            } catch (Throwable $e) {
                $this->warn("AST traverse hatası: {$filePath} - {$e->getMessage()}");
                continue;
            }
        }

        return $queries;
    }
}
