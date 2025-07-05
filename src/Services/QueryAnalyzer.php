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
        $minQueryTime = config('index-analyzer.suggestions.min_query_time', 0);
        $minQueryCount = config('index-analyzer.suggestions.min_query_count', 1);

        // Boş sorgu kontrolü
        if (empty($queries)) {
            return [];
        }

        // Her analiz başlangıcında öneri tablosunu temizle
        $this->tableSuggestions = [];

        // Group queries by table and conditions
        $groupedQueries = [];

        foreach ($queries as $query) {
            // Süre filtreleme (eğer aktifse)
            if (isset($query['time']) && $minQueryTime > 0 && $query['time'] < $minQueryTime) {
                continue;
            }

            // SQL'i ayrıştır ve tablo önerilerini oluştur
            $parsedQuery = $this->parseQuery($query['sql']);
            if (empty($parsedQuery)) {
                continue;
            }

            // Ana tablo için öneri oluştur (eğer yok sayılan bir tablo değilse)
            if (!in_array($parsedQuery['table'], $ignoredTables)) {
                $key = $parsedQuery['table'] . ':' . implode(',', $parsedQuery['where_columns']);

                if (!isset($groupedQueries[$key])) {
                    $groupedQueries[$key] = [
                        'table' => $parsedQuery['table'],
                        'columns' => $parsedQuery['where_columns'],
                        'count' => 0,
                        'total_time' => 0,
                        'query_type' => $parsedQuery['query_type'] ?? 'SELECT',
                    ];
                }

                $groupedQueries[$key]['count']++;
                $groupedQueries[$key]['total_time'] += $query['time'] ?? 0;
            }
        }

        // Analiz sonucunda oluşan tüm tablo önerilerini ekleyelim
        foreach ($this->tableSuggestions as $tableName => $columns) {
            // Yok sayılan tablolar ve boş sütun listelerini atla
            if (in_array($tableName, $ignoredTables) || empty($columns)) {
                continue;
            }

            $key = $tableName . ':' . implode(',', $columns);

            // Eğer bu tablo+sütun kombinasyonu zaten ana öneri listesinde varsa atlayalım
            if (isset($groupedQueries[$key])) {
                continue;
            }

            // JOIN, GROUP BY veya ORDER BY ile kullanılan tablo/sütunlar için öneri ekle
            $groupedQueries[$key] = [
                'table' => $tableName,
                'columns' => $columns,
                'count' => 1, // En az bir kez kullanıldığını varsayıyoruz
                'total_time' => 0, // Süre bilgisini JOIN sorgusundan alamıyoruz
                'join_related' => true, // Bu, JOIN ile ilgili bir öneri
            ];
        }

        // Filter by query count and check existing indexes
        foreach ($groupedQueries as $key => $group) {
            // Sayaç kontrolü
            if ($group['count'] < $minQueryCount) {
                continue;
            }

            // Boş sütun listesi kontrolü
            if (empty($group['columns'])) {
                continue;
            }

            // Mevcut indeks kontrolü
            if (!$this->isIndexNeeded($group['table'], $group['columns'])) {
                continue;
            }

            // Öneriyi ekle
            $suggestions[] = [
                'table' => $group['table'],
                'columns' => $group['columns'],
                'query_count' => $group['count'],
                'avg_time' => $group['total_time'] / $group['count'],
                'index_name' => $this->generateIndexName($group['table'], $group['columns']),
                'join_related' => $group['join_related'] ?? false,
                'query_type' => $group['query_type'] ?? 'SELECT',
            ];
        }

        // Analiz bittikten sonra öneri tablosunu temizle
        $this->tableSuggestions = [];

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

        // We're only interested in SELECT, UPDATE and DELETE queries
        $upperSql = strtoupper($sql);
        if (!Str::startsWith($upperSql, 'SELECT') &&
            !Str::startsWith($upperSql, 'UPDATE') &&
            !Str::startsWith($upperSql, 'DELETE')) {
            return null;
        }

        // Sorgu türünü belirle
        $queryType = 'SELECT';
        if (Str::startsWith($upperSql, 'UPDATE')) {
            $queryType = 'UPDATE';
        } elseif (Str::startsWith($upperSql, 'DELETE')) {
            $queryType = 'DELETE';
        }

        // Tüm tabloları ve join bilgilerini topla
        $tables = [];
        $joinColumns = [];
        $whereColumns = [];

        // Ana tablo adını çıkar (sorgu tipine göre farklı işlem yapılır)
        if ($queryType === 'SELECT') {
            // SELECT için FROM kısmını bul
            $fromMatch = [];
            if (!preg_match('/\bFROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?(?:\s+(?:AS\s+)?[`"]?([a-zA-Z0-9_]+)[`"]?)?/i', $sql, $fromMatch)) {
                return null;
            }

            $mainTable = $fromMatch[1];
            $mainTableAlias = !empty($fromMatch[2]) ? $fromMatch[2] : $mainTable;
            $tables[$mainTableAlias] = $mainTable;
        } elseif ($queryType === 'UPDATE') {
            // UPDATE için tablo adını doğrudan al
            $updateMatch = [];
            if (!preg_match('/UPDATE\s+[`"]?([a-zA-Z0-9_]+)[`"]?(?:\s+(?:AS\s+)?[`"]?([a-zA-Z0-9_]+)[`"]?)?/i', $sql, $updateMatch)) {
                return null;
            }

            $mainTable = $updateMatch[1];
            $mainTableAlias = !empty($updateMatch[2]) ? $updateMatch[2] : $mainTable;
            $tables[$mainTableAlias] = $mainTable;
        } elseif ($queryType === 'DELETE') {
            // DELETE için FROM kısmını bul
            $deleteMatch = [];
            if (!preg_match('/DELETE\s+FROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?(?:\s+(?:AS\s+)?[`"]?([a-zA-Z0-9_]+)[`"]?)?/i', $sql, $deleteMatch)) {
                return null;
            }

            $mainTable = $deleteMatch[1];
            $mainTableAlias = !empty($deleteMatch[2]) ? $deleteMatch[2] : $mainTable;
            $tables[$mainTableAlias] = $mainTable;
        }

        // JOIN ifadelerini çıkar
        $joinPattern = '/\b(INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+[`"]?([a-zA-Z0-9_]+)[`"]?(?:\s+(?:AS\s+)?[`"]?([a-zA-Z0-9_]+)[`"]?)?\s+ON\s+(.+?)(?=\s+(?:INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+|\s+WHERE\s+|\s+GROUP\s+BY|\s+ORDER\s+BY|\s+LIMIT|$)/is';
        preg_match_all($joinPattern, $sql, $joinMatches, PREG_SET_ORDER);

        foreach ($joinMatches as $match) {
            $joinTable = $match[2];
            $joinAlias = !empty($match[3]) ? $match[3] : $joinTable;
            $joinCondition = $match[4];

            $tables[$joinAlias] = $joinTable;

            // JOIN koşulundaki sütunları çıkar (indeksleme için önemli)
            preg_match_all('/([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*=\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)/i', $joinCondition, $joinColumnMatches, PREG_SET_ORDER);

            foreach ($joinColumnMatches as $colMatch) {
                $leftAlias = $colMatch[1];
                $leftColumn = $colMatch[2];
                $rightAlias = $colMatch[3];
                $rightColumn = $colMatch[4];

                // JOIN koşullarındaki sütunlar genellikle indekslenmesi gereken sütunlardır
                if (isset($tables[$leftAlias])) {
                    $joinColumns[] = [
                        'table' => $tables[$leftAlias],
                        'column' => $leftColumn,
                        'alias' => $leftAlias
                    ];
                }

                if (isset($tables[$rightAlias])) {
                    $joinColumns[] = [
                        'table' => $tables[$rightAlias],
                        'column' => $rightColumn,
                        'alias' => $rightAlias
                    ];
                }
            }
        }

        // WHERE koşullarını çıkar
        $whereMatch = [];

        if (preg_match('/\bWHERE\s+(.+?)(?:\s+GROUP\s+BY|\s+ORDER\s+BY|\s+LIMIT|\s+HAVING|$)/is', $sql, $whereMatch)) {
            $whereClause = $whereMatch[1];

            // Her tablo için WHERE koşullarındaki sütunları çıkar
            foreach ($tables as $alias => $tableName) {
                // Alias.column şeklindeki koşulları ara
                preg_match_all('/' . preg_quote($alias, '/') . '\.([a-zA-Z0-9_]+)\s*(?:=|>|<|>=|<=|<>|!=|LIKE|IS|IN|NOT IN|BETWEEN)/i', $whereClause, $aliasColumnMatches);

                if (!empty($aliasColumnMatches[1])) {
                    foreach ($aliasColumnMatches[1] as $column) {
                        $whereColumns[] = [
                            'table' => $tableName,
                            'column' => $column,
                            'alias' => $alias
                        ];
                    }
                }

                // Tabloya özgü, alias olmadan direkt sütun adları (sadece ana tablo için)
                if ($alias === $mainTableAlias || $alias === $mainTable) {
                    // Nokta olmadan ama başka alias olmayan sütunları bul
                    preg_match_all('/(?<!\w\.)\b([a-zA-Z0-9_]+)\s*(?:=|>|<|>=|<=|<>|!=|LIKE|IS|IN|NOT IN|BETWEEN)/i', $whereClause, $directColumnMatches);

                    if (!empty($directColumnMatches[1])) {
                        foreach ($directColumnMatches[1] as $column) {
                            // SQL anahtar kelimeleri hariç
                            if (!in_array(strtoupper($column), ['AND', 'OR', 'NULL', 'NOT', 'IS', 'IN', 'LIKE', 'BETWEEN'])) {
                                $whereColumns[] = [
                                    'table' => $tableName,
                                    'column' => $column,
                                    'alias' => $alias
                                ];
                            }
                        }
                    }
                }
            }
        }

        // ORDER BY ve GROUP BY sütunlarını da topla (bunlar da indeks kullanabilir)
        $orderGroupColumns = [];

        // ORDER BY
        if (preg_match('/\bORDER\s+BY\s+(.+?)(?:\s+LIMIT|$)/is', $sql, $orderMatch)) {
            preg_match_all('/([a-zA-Z0-9_]+)(?:\.([a-zA-Z0-9_]+))?/i', $orderMatch[1], $orderColumns, PREG_SET_ORDER);

            foreach ($orderColumns as $colMatch) {
                if (isset($colMatch[2])) { // table.column biçimi
                    $alias = $colMatch[1];
                    $column = $colMatch[2];

                    if (isset($tables[$alias])) {
                        $orderGroupColumns[] = [
                            'table' => $tables[$alias],
                            'column' => $column,
                            'alias' => $alias
                        ];
                    }
                } else { // Sadece column adı
                    $column = $colMatch[1];

                    // Varsayılan olarak ana tabloya ait kabul et
                    $orderGroupColumns[] = [
                        'table' => $mainTable,
                        'column' => $column,
                        'alias' => $mainTableAlias
                    ];
                }
            }
        }

        // GROUP BY
        if (preg_match('/\bGROUP\s+BY\s+(.+?)(?:\s+HAVING|\s+ORDER|\s+LIMIT|$)/is', $sql, $groupMatch)) {
            preg_match_all('/([a-zA-Z0-9_]+)(?:\.([a-zA-Z0-9_]+))?/i', $groupMatch[1], $groupColumns, PREG_SET_ORDER);

            foreach ($groupColumns as $colMatch) {
                if (isset($colMatch[2])) { // table.column biçimi
                    $alias = $colMatch[1];
                    $column = $colMatch[2];

                    if (isset($tables[$alias])) {
                        $orderGroupColumns[] = [
                            'table' => $tables[$alias],
                            'column' => $column,
                            'alias' => $alias
                        ];
                    }
                } else { // Sadece column adı
                    $column = $colMatch[1];

                    // Varsayılan olarak ana tabloya ait kabul et
                    $orderGroupColumns[] = [
                        'table' => $mainTable,
                        'column' => $column,
                        'alias' => $mainTableAlias
                    ];
                }
            }
        }

        // Tüm bilgileri topla ve döndür
        $result = [
            'table' => $mainTable,
            'alias' => $mainTableAlias,
            'query_type' => $queryType,
            'tables' => $tables,
            'join_columns' => $joinColumns,
            'where_columns' => $whereColumns,
            'order_group_columns' => $orderGroupColumns
        ];

        // Tüm sütunları birleştir (alias filtrelemesi ile)
        $filteredColumns = [];

        // JOIN sütunları
        foreach ($joinColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // WHERE sütunları
        foreach ($whereColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // ORDER BY ve GROUP BY sütunları
        foreach ($orderGroupColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // Benzersiz sütun listesi oluştur
        $result['where_columns'] = array_unique($filteredColumns);

        // Ek tablo önerileri için analizler
        $this->analyzeTablesForSuggestions($result);

        return $result;
    }

    /**
     * Sorgu analizi sırasında bulduğumuz tablo ve sütun bilgilerini saklayan değişken
     *
     * @var array
     */
    protected $tableSuggestions = [];

    /**
     * Analiz edilen sorguda yer alan tüm tablolar için indeks önerileri oluştur
     *
     * @param array $queryData
     * @return void
     */
    protected function analyzeTablesForSuggestions(array $queryData): void
    {
        if (empty($queryData['tables'])) {
            return;
        }

        // Her tablo için indeks önerileri oluştur
        foreach ($queryData['tables'] as $alias => $tableName) {
            if (!isset($this->tableSuggestions[$tableName])) {
                $this->tableSuggestions[$tableName] = [];
            }

            // JOIN sütunları - Bu tabloya ait olanları ekle
            foreach ($queryData['join_columns'] as $col) {
                if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                    $this->tableSuggestions[$tableName][] = $col['column'];
                }
            }

            // WHERE sütunları - Bu tabloya ait olanları ekle
            foreach ($queryData['where_columns'] as $col) {
                if (is_array($col) && $col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                    $this->tableSuggestions[$tableName][] = $col['column'];
                }
            }

            // ORDER BY ve GROUP BY sütunları - Bu tabloya ait olanları ekle
            foreach ($queryData['order_group_columns'] as $col) {
                if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                    $this->tableSuggestions[$tableName][] = $col['column'];
                }
            }
        }
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
