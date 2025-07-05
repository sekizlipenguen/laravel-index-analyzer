<?php

namespace SekizliPenguen\IndexAnalyzer\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class QueryLogger
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Stored queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Create a new query logger instance.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Log a SQL query.
     *
     * @param \Illuminate\Database\Events\QueryExecuted $query
     * @return void
     */
    public function logQuery(QueryExecuted $query)
    {
        // Ignore certain query types
        if ($this->shouldIgnoreQuery($query->sql)) {
            return;
        }

        $queryData = [
            'sql' => $this->formatSql($query->sql),
            'bindings' => $query->bindings,
            'time' => $query->time,
            'connection' => $query->connectionName,
            'hash' => $this->generateQueryHash($query),
            'url' => request()->fullUrl(),
            'timestamp' => microtime(true),
        ];

        // Only log unique queries based on hash
        if (!$this->isDuplicateQuery($queryData['hash'])) {
            $this->storeQuery($queryData);
        }
    }

    /**
     * Check if a query should be ignored.
     *
     * @param string $sql
     * @return bool
     */
    protected function shouldIgnoreQuery($sql)
    {
        $ignoredPatterns = [
            'SHOW FULL TABLES',
            'INFORMATION_SCHEMA',
            'SHOW INDEXES',
            'pg_catalog',
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (Str::contains($sql, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format the SQL query.
     *
     * @param string $sql
     * @return string
     */
    protected function formatSql($sql)
    {
        return $sql;
    }

    /**
     * Generate a unique hash for the query.
     *
     * @param \Illuminate\Database\Events\QueryExecuted $query
     * @return string
     */
    protected function generateQueryHash(QueryExecuted $query)
    {
        return md5($query->sql . serialize($query->bindings));
    }

    /**
     * Check if the query with the given hash has already been logged.
     *
     * @param string $hash
     * @return bool
     */
    protected function isDuplicateQuery($hash)
    {
        foreach ($this->queries as $query) {
            if ($query['hash'] === $hash) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store the query data.
     *
     * @param array $queryData
     * @return void
     */
    protected function storeQuery(array $queryData)
    {
        $this->queries[] = $queryData;

        // Store to the configured storage
        if (config('index-analyzer.storage') === 'file') {
            $this->storeToFile($queryData);
        }
    }

    /**
     * Store the query data to a file.
     *
     * @param array $queryData
     * @return void
     */
    protected function storeToFile(array $queryData)
    {
        $logPath = config('index-analyzer.log_path');
        $jsonData = json_encode($queryData) . PHP_EOL;

        File::append($logPath, $jsonData);
    }

    /**
     * Get all the stored queries.
     *
     * @return array
     */
    public function getQueries()
    {
        // If using file storage, load queries from file
        if (config('index-analyzer.storage') === 'file') {
            return $this->loadQueriesFromFile();
        }

        return $this->queries;
    }

    /**
     * Load queries from the log file.
     *
     * @return array
     */
    protected function loadQueriesFromFile()
    {
        $logPath = config('index-analyzer.log_path');

        if (!File::exists($logPath)) {
            return [];
        }

        $queries = [];
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $queries[] = json_decode($line, true);
        }

        return $queries;
    }

    /**
     * Clear all stored queries.
     *
     * @return void
     */
    public function clearQueries()
    {
        $this->queries = [];

        if (config('index-analyzer.storage') === 'file') {
            $logPath = config('index-analyzer.log_path');

            if (File::exists($logPath)) {
                File::put($logPath, '');
            }
        }
    }
}
