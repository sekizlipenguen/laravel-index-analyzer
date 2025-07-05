<?php

namespace SekizliPenguen\IndexAnalyzer\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueryAnalyzer
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Cached indexes keyed by table name.
     *
     * @var array
     */
    protected array $cachedIndexes = [];

    /**
     * Create a new query analyzer instance.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Analyze the given queries and generate index suggestions.
     *
     * @param array $queries
     * @return array
     */
    public function analyze(array $queries): array
    {
        $suggestions = [];
        $ignoredTables = config('index-analyzer.suggestions.ignore_tables', []);
        $minQueryTime = config('index-analyzer.suggestions.min_query_time', 0); // 0'a düşürüldü
        $minQueryCount = config('index-analyzer.suggestions.min_query_count', 1); // 1'e düşürüldü

        // Group queries by table and conditions
        $groupedQueries = [];

        foreach ($queries as $query) {
            // Hızlı sorguları atlama kontrolü kaldırıldı
            // if ($query['time'] < $minQueryTime) {
            //     continue;
            // }

            $parsedQuery = $this->parseQuery($query['sql']);

            if (empty($parsedQuery) || in_array($parsedQuery['table'], $ignoredTables)) {
                continue;
            }

            $key = $parsedQuery['table'] . ':' . implode(',', $parsedQuery['where_columns']);

            if (!isset($groupedQueries[$key])) {
                $groupedQueries[$key] = [
                    'table' => $parsedQuery['table'],
                    'columns' => $parsedQuery['where_columns'],
                    'count' => 0,
                    'total_time' => 0,
                ];
            }

            $groupedQueries[$key]['count']++;
            $groupedQueries[$key]['total_time'] += $query['time'];
        }

        // Filter by query count and check existing indexes
        foreach ($groupedQueries as $key => $group) {
            if ($group['count'] < $minQueryCount) {
                continue;
            }

            // Check if index already exists
            if (!$this->isIndexNeeded($group['table'], $group['columns'])) {
                continue;
            }

            $suggestions[] = [
                'table' => $group['table'],
                'columns' => $group['columns'],
                'query_count' => $group['count'],
                'avg_time' => $group['total_time'] / $group['count'],
                'index_name' => $this->generateIndexName($group['table'], $group['columns']),
            ];
        }

        return $suggestions;
    }

    /**
     * Parse a SQL query to extract table and where columns.
     *
     * @param string $sql
     * @return array|null
     */
    protected function parseQuery(string $sql): ?array
    {
        // Simple SQL parsing - in a real implementation, this would be more robust
        $sql = trim($sql);

        // We're only interested in SELECT queries with WHERE clauses
        if (!Str::startsWith(strtoupper($sql), 'SELECT')) {
            return null;
        }

        // Extract table name
        $fromMatch = [];
        if (!preg_match('/\bFROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $fromMatch)) {
            return null;
        }

        $table = $fromMatch[1];

        // Extract WHERE conditions
        $whereColumns = [];
        $whereMatch = [];

        if (preg_match('/\bWHERE\s+(.+?)(?:\bORDER BY|\bGROUP BY|\bLIMIT|\bHAVING|$)/is', $sql, $whereMatch)) {
            $whereClause = $whereMatch[1];

            // Extract column names from WHERE conditions
            preg_match_all('/[`"]?([a-zA-Z0-9_]+)[`"]?\s*(?:=|>|<|>=|<=|<>|!=|LIKE|IN)/i', $whereClause, $columnMatches);

            if (!empty($columnMatches[1])) {
                $whereColumns = array_unique($columnMatches[1]);
            }
        }

        return [
            'table' => $table,
            'where_columns' => $whereColumns,
        ];
    }

    /**
     * Check if an index is needed for the given table and columns.
     *
     * @param string $table
     * @param array $columns
     * @return bool
     */
    protected function isIndexNeeded($table, array $columns)
    {
        if (empty($columns)) {
            return false;
        }

        $existingIndexes = $this->getTableIndexes($table);

        // Check if any existing index covers these columns
        foreach ($existingIndexes as $index) {
            $indexColumns = $index['columns'];

            // For simplicity, we're only checking for exact matches
            // A more sophisticated approach would check for index prefix matches
            if ($this->isSubset($columns, $indexColumns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the indexes for a table.
     *
     * @param string $table
     * @return array
     */
    protected function getTableIndexes($table)
    {
        if (isset($this->cachedIndexes[$table])) {
            return $this->cachedIndexes[$table];
        }

        try {
            $indexes = [];
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $rawIndexes = DB::select("SHOW INDEXES FROM `{$table}` WHERE `Key_name` != 'PRIMARY'");

                $groupedIndexes = [];

                foreach ($rawIndexes as $index) {
                    $keyName = $index->Key_name;

                    if (!isset($groupedIndexes[$keyName])) {
                        $groupedIndexes[$keyName] = [
                            'name' => $keyName,
                            'columns' => [],
                        ];
                    }

                    $groupedIndexes[$keyName]['columns'][] = $index->Column_name;
                }

                $indexes = array_values($groupedIndexes);
            } else if ($driver === 'sqlite') {
                $rawIndexes = DB::select("PRAGMA index_list({$table})");

                foreach ($rawIndexes as $index) {
                    if ($index->origin === 'pk') {
                        continue;
                    }

                    $indexInfo = DB::select("PRAGMA index_info({$index->name})");
                    $columns = array_map(function ($column) {
                        return $column->name;
                    }, $indexInfo);

                    $indexes[] = [
                        'name' => $index->name,
                        'columns' => $columns,
                    ];
                }
            } else if ($driver === 'pgsql') {
                $schema = config('database.connections.pgsql.schema', 'public');
                $rawIndexes = DB::select(
                    "SELECT i.relname as name, a.attname as column_name " .
                    "FROM pg_index x " .
                    "JOIN pg_class c ON c.oid = x.indrelid " .
                    "JOIN pg_class i ON i.oid = x.indexrelid " .
                    "JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(x.indkey) " .
                    "LEFT JOIN pg_namespace n ON n.oid = c.relnamespace " .
                    "WHERE c.relkind = 'r' AND i.relkind = 'i' " .
                    "AND n.nspname = '{$schema}' AND c.relname = '{$table}' " .
                    "AND NOT x.indisprimary " .
                    "ORDER BY i.relname, a.attnum"
                );

                $groupedIndexes = [];

                foreach ($rawIndexes as $index) {
                    $keyName = $index->name;

                    if (!isset($groupedIndexes[$keyName])) {
                        $groupedIndexes[$keyName] = [
                            'name' => $keyName,
                            'columns' => [],
                        ];
                    }

                    $groupedIndexes[$keyName]['columns'][] = $index->column_name;
                }

                $indexes = array_values($groupedIndexes);
            }

            $this->cachedIndexes[$table] = $indexes;

            return $indexes;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if the first array is a subset of the second array.
     *
     * @param array $subset
     * @param array $set
     * @return bool
     */
    protected function isSubset(array $subset, array $set)
    {
        if (empty($subset)) {
            return true;
        }

        $count = count(array_intersect($subset, $set));
        return $count === count($subset);
    }

    /**
     * Generate an index name for the given table and columns.
     *
     * @param string $table
     * @param array $columns
     * @return string
     */
    protected function generateIndexName($table, array $columns)
    {
        return $table . '_' . implode('_', $columns) . '_idx';
    }
}
