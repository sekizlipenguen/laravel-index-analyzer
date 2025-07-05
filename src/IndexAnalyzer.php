<?php

namespace SekizliPenguen\IndexAnalyzer;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use SekizliPenguen\IndexAnalyzer\Services\QueryAnalyzer;
use SekizliPenguen\IndexAnalyzer\Services\QueryLogger;

class IndexAnalyzer
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The query logger instance.
     *
     * @var \SekizliPenguen\IndexAnalyzer\Services\QueryLogger
     */
    protected $queryLogger;

    /**
     * The query analyzer instance.
     *
     * @var \SekizliPenguen\IndexAnalyzer\Services\QueryAnalyzer
     */
    protected $queryAnalyzer;

    /**
     * Create a new index analyzer instance.
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
     * Start capturing database queries.
     *
     * @return void
     */
    public function startCapturing()
    {
        DB::listen(function ($query) {
            $this->queryLogger->logQuery($query);
        });
    }

    /**
     * Generate SQL statements for the suggested indexes.
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
     * Generate index suggestions based on the captured queries.
     *
     * @return array
     */
    public function generateSuggestions()
    {
        $queries = $this->queryLogger->getQueries();
        return $this->queryAnalyzer->analyze($queries);
    }

    /**
     * Build an ADD INDEX SQL statement.
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
}
