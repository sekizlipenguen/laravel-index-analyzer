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
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Sorgu günlükleyici örneği.
     *
     * @var \SekizliPenguen\IndexAnalyzer\Services\QueryLogger
     */
    protected $queryLogger;

    /**
     * Sorgu analizci örneği.
     *
     * @var \SekizliPenguen\IndexAnalyzer\Services\QueryAnalyzer
     */
    protected $queryAnalyzer;

    /**
     * Yeni bir indeks analizci örneği oluştur.
     *
     * @param \Illuminate\Foundation\Application $app
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
            $statements[] = $this->buildAddIndexStatement(
                $suggestion['table'],
                $suggestion['columns'],
                $suggestion['index_name'] ?? null
            );
        }

        return $statements;
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
        $indexName = $indexName ?: $table . '_' . implode('_', $columns) . '_idx';
        $columnList = implode(',', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));

        return "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnList});";
    }

    /**
     * Sorgu günlükleyici örneğini al.
     *
     * @return \SekizliPenguen\IndexAnalyzer\Services\QueryLogger
     */
    public function getQueryLogger()
    {
        return $this->queryLogger;
    }
}
