<?php

namespace SekizliPenguen\IndexAnalyzer;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use SekizliPenguen\IndexAnalyzer\Services\QueryAnalyzer;
use SekizliPenguen\IndexAnalyzer\Services\QueryLogger;

class IndexAnalyzer
{
    /**
     * Uygulama örneği.
     *
     * @var Application
     */
    protected $app;

    /**
     * Sorgu günlükleyici örneği.
     *
     * @var QueryLogger
     */
    protected $queryLogger;

    /**
     * Sorgu analizci örneği.
     *
     * @var QueryAnalyzer
     */
    protected $queryAnalyzer;

    /**
     * Yeni bir indeks analizci örneği oluştur.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->queryLogger = new QueryLogger($app);
        $this->queryAnalyzer = new QueryAnalyzer($app);
    }

    /**
     * Veritabanı sorgularını yakalamaya başla.
     *
     * @return bool
     */
    public function startCapturing(): bool
    {
        // DB::listen ile tüm sorguları dinle
        DB::listen(function ($query) {
            $this->queryLogger->logQuery($query);
        });

        // Başarıyla başladığını döndür
        return true;
    }

    /**
     * Önerilen indeksler için SQL ifadeleri oluştur.
     *
     * @return array
     */
    public function generateIndexStatements()
    {
        $suggestions = $this->generateSuggestions();

        $statements = [];
        foreach ($suggestions as $suggestion) {
            // Indeks adı maksimum uzunluk kontrolü
            $indexName = $suggestion['index_name'] ?? null;
            if ($indexName && strlen($indexName) > 64) {
                $table = $suggestion['table'];
                $columns = $suggestion['columns'];
                $indexName = $this->queryAnalyzer->generateIndexName($table, $columns);
            }

            $statement = $this->buildAddIndexStatement(
                $suggestion['table'],
                $suggestion['columns'],
                $indexName
            );

            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Veritabanında zaten var olan önerilen indeksler için SQL ifadeleri oluşturur.
     *
     * @return array
     */
    public function generateExistingIndexStatements()
    {
        $existingSuggestions = $this->generateExistingSuggestions();

        $statements = [];

        foreach ($existingSuggestions as $suggestion) {
            $statement = $this->buildAddIndexStatement(
                $suggestion['table'],
                $suggestion['columns'],
                $suggestion['index_name'] ?? null
            );

            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Veritabanında zaten var olan indeksler için önerileri oluştur.
     *
     * @return array
     */
    public function generateExistingSuggestions()
    {
        $queries = $this->queryLogger->getQueries();
        return $this->queryAnalyzer->analyzeExisting($queries);
    }

    /**
     * Yakalanan sorgulara dayalı indeks önerileri oluştur.
     *
     * @return array
     */
    public function generateSuggestions()
    {
        $queries = $this->queryLogger->getQueries();
        return $this->queryAnalyzer->analyze($queries);
    }

    /**
     * Bir ADD INDEX SQL ifadesi oluştur.
     *
     * @param string $table
     * @param array $columns
     * @param string|null $indexName
     * @return string
     */
    protected function buildAddIndexStatement($table, $columns, $indexName = null)
    {
        // Alt sorgular (subquery) için indeks ifadesi oluşturma
        if (strpos($table, 'subquery_') === 0) {
            return null;
        }

        // İndeks ismi uzunluğunu kontrol et - MySQL'de maksimum 64 karakter
        $maxIndexNameLength = 64;

        // Tablonun gerçekten var olup olmadığını kontrol et
        try {
            if (!DB::connection()->getSchemaBuilder()->hasTable($table)) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        // Tüm sütunların gerçekten var olup olmadığını kontrol et
        foreach ($columns as $key => $column) {
            try {
                if (!DB::connection()->getSchemaBuilder()->hasColumn($table, $column)) {
                    // Geçersiz sütunları listeden çıkar
                    unset($columns[$key]);
                }
            } catch (\Exception $e) {
                // Hata durumunda sütunu listeden çıkar
                unset($columns[$key]);
            }
        }

        // Eğer hiç geçerli sütun kalmadıysa, indeks oluşturma
        if (empty($columns)) {
            return null;
        }

        // Sütun dizisini yeniden indeksle
        $columns = array_values($columns);

        // İndeks ismini oluştur
        if (!$indexName) {
            // QueryAnalyzer'daki generateIndexName metodunu kullan - bu zaten optimize edilmiş
            $indexName = $this->queryAnalyzer->generateIndexName($table, $columns);
            // Son bir kontrol
            if (strlen($indexName) > $maxIndexNameLength) {
                // En son çare - gerçekten çok uzunsa, tablo adını kısalt ve tamamen hash kullan
                $shortTableName = strlen($table) > 10 ? substr($table, 0, 10) : $table;
                $hash = substr(md5(implode('_', $columns)), 0, 15);
                $indexName = $shortTableName . '_' . $hash . '_idx';

                // Yine de uzunsa en sonunda sadece kırp
                if (strlen($indexName) > $maxIndexNameLength) {
                    $indexName = substr($indexName, 0, $maxIndexNameLength - 1);
                }
            }
        }

        $columnList = implode(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));

        return "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnList});";
    }

    /**
     * Sorgu günlükleyici örneğini al.
     *
     * @return QueryLogger
     */
    public function getQueryLogger()
    {
        return $this->queryLogger;
    }

    /**
     * Sorgu analizci örneğini al.
     *
     * @return QueryAnalyzer
     */
    public function getQueryAnalyzer()
    {
        return $this->queryAnalyzer;
    }
}
