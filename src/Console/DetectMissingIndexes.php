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

        $queries = $this->extractQueryChains($phpFiles);

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

        foreach ($queries as $query) {
            $filename = $outputPath . '/query_' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '_' . substr(md5($query), 0, 6) . '.php';
            $script = "<?php\n\nuse Illuminate\\Support\\Facades\\DB;\n\n// 💡 Otomatik tespit edilen sorgu bloğu:\n\nreturn DB::pretend(function () {\n    {$query}\n});\n";
            File::put($filename, $script);
            // Immediately execute and collect SQL queries
            try {
                $result = include $filename;
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
                // Optionally log or ignore errors
                $errorCount++;
                if ($this->option('verbose')) {
                    $this->warn("⚠️ Sorgu simülasyonu hatası: " . $e->getMessage());
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

    protected function extractQueryChains($phpFiles): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $queries = [];
        $pretty = new Standard();
        $modelPaths = config('index-analyzer.model_paths', ['app/Models']);

        foreach ($phpFiles as $file) {
            try {
                $code = file_get_contents($file->getRealPath());
                $ast = $parser->parse($code);

                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NameResolver());
                $traverser->addVisitor(new class($queries, $pretty, $modelPaths) extends NodeVisitorAbstract {
                    public $queries;
                    public $pretty;
                    public $modelPaths;
                    public $currentChain = [];
                    public $inQuery = false;

                    public function __construct(&$queries, $pretty, $modelPaths)
                    {
                        $this->queries = &$queries;
                        $this->pretty = $pretty;
                        $this->modelPaths = $modelPaths;
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
                                        $this->queries[] = $fullChain;
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
                $this->warn("AST parse hatası: {$file->getRealPath()} - {$e->getMessage()}");
                continue;
            }
        }

        return $queries;
    }
}
