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
        // SQL sorgusunu temizle ve normalize et
        $sql = $this->normalizeSql($sql);

        // Sorgu türünü belirle
        $upperSql = strtoupper($sql);
        if (Str::startsWith($upperSql, 'SELECT')) {
            $queryType = 'SELECT';
        } elseif (Str::startsWith($upperSql, 'UPDATE')) {
            $queryType = 'UPDATE';
        } elseif (Str::startsWith($upperSql, 'DELETE')) {
            $queryType = 'DELETE';
        } else {
            // Desteklenmeyen sorgu tipi
            return null;
        }

        // Tüm tabloları ve sütunları saklamak için diziler
        $tables = [];
        $mainTable = null;
        $mainTableAlias = null;
        $joinColumns = [];
        $whereColumns = [];
        $havingColumns = [];
        $orderByColumns = [];
        $groupByColumns = [];
        $selectColumns = [];
        $inClauseColumns = [];
        $caseWhenColumns = [];
        $columnAliases = [];
        $subqueryTables = [];

        // Sorgu tipine göre ana tabloyu belirle
        if ($queryType === 'SELECT') {
            // Alt sorguları analiz et
            $this->extractAndParseSubqueries($sql);

            // SELECT ifadelerinden sütunları çıkar
            $this->extractSelectColumns($sql, $selectColumns, $columnAliases);

            // FROM ifadesinden ana tabloyu çıkar
            $fromInfo = $this->extractFromTable($sql);
            if (!$fromInfo) {
                return null; // FROM bulunamadı, bu sorguyu analiz edemiyoruz
            }

            $mainTable = $fromInfo['table'];
            $mainTableAlias = $fromInfo['alias'];
            $tables[$mainTableAlias] = $mainTable;

            // Alt tablo sorguları için kontrol et
            if ($fromInfo['is_subquery']) {
                $subqueryTables[] = $mainTable;
            }
        } elseif ($queryType === 'UPDATE') {
            // UPDATE için tablo adını doğrudan al
            $updateInfo = $this->extractUpdateTable($sql);
            if (!$updateInfo) {
                return null; // UPDATE tablosu bulunamadı
            }

            $mainTable = $updateInfo['table'];
            $mainTableAlias = $updateInfo['alias'];
            $tables[$mainTableAlias] = $mainTable;
        } elseif ($queryType === 'DELETE') {
            // DELETE için FROM kısmını bul
            $deleteInfo = $this->extractDeleteTable($sql);
            if (!$deleteInfo) {
                return null; // DELETE FROM bulunamadı
            }

            $mainTable = $deleteInfo['table'];
            $mainTableAlias = $deleteInfo['alias'];
            $tables[$mainTableAlias] = $mainTable;
        }

        // JOIN ifadelerini analiz et
        $joinTables = $this->extractJoinTables($sql);

        foreach ($joinTables as $joinInfo) {
            $joinTable = $joinInfo['table'];
            $joinAlias = $joinInfo['alias'];
            $joinCondition = $joinInfo['condition'];

            $tables[$joinAlias] = $joinTable;

            // JOIN koşullarını analiz et
            $this->analyzeJoinCondition($joinCondition, $tables, $joinColumns);

            // Alt tablo sorguları için kontrol et
            if ($joinInfo['is_subquery']) {
                $subqueryTables[] = $joinTable;
            }
        }

        // WHERE koşullarını analiz et
        $whereClause = $this->extractWhereClause($sql);
        if ($whereClause) {
            $this->analyzeWhereClause($whereClause, $tables, $whereColumns, $inClauseColumns, $mainTableAlias, $mainTable);
        }

        // HAVING koşullarını analiz et
        $havingClause = $this->extractHavingClause($sql);
        if ($havingClause) {
            $this->analyzeHavingClause($havingClause, $tables, $havingColumns, $mainTableAlias, $mainTable);
        }

        // GROUP BY ifadelerini analiz et
        $groupByClause = $this->extractGroupByClause($sql);
        if ($groupByClause) {
            $this->analyzeGroupByClause($groupByClause, $tables, $groupByColumns, $mainTableAlias, $mainTable);
        }

        // ORDER BY ifadelerini analiz et
        $orderByClause = $this->extractOrderByClause($sql);
        if ($orderByClause) {
            $this->analyzeOrderByClause($orderByClause, $tables, $orderByColumns, $mainTableAlias, $mainTable);
        }

        // CASE/WHEN/IF koşullarını ayrıştır
        $this->analyzeCaseWhenStatements($sql, $tables, $caseWhenColumns, $mainTableAlias, $mainTable);

        // Tüm sütunları birleştir (alias filtrelemesi ile) - sadece ana tablo için
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

        // HAVING sütunları
        foreach ($havingColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // GROUP BY sütunları
        foreach ($groupByColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // ORDER BY sütunları
        foreach ($orderByColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // IN clause sütunları
        foreach ($inClauseColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // CASE/WHEN/IF koşullarındaki sütunlar
        foreach ($caseWhenColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // SELECT içindeki ana tablo sütunları
        foreach ($selectColumns as $col) {
            if ($col['table'] === $mainTable) {
                $filteredColumns[] = $col['column'];
            }
        }

        // Benzersiz sütun listesi oluştur
        $uniqueColumns = array_unique($filteredColumns);

        // Ek tablo önerileri için analizler yapmak üzere tüm verileri toplayalım
        $result = [
            'table' => $mainTable,
            'alias' => $mainTableAlias,
            'query_type' => $queryType,
            'tables' => $tables,
            'subquery_tables' => $subqueryTables,
            'join_columns' => $joinColumns,
            'where_columns' => $whereColumns,
            'having_columns' => $havingColumns,
            'order_by_columns' => $orderByColumns,
            'group_by_columns' => $groupByColumns,
            'select_columns' => $selectColumns,
            'in_clause_columns' => $inClauseColumns,
            'case_when_columns' => $caseWhenColumns,
            'column_aliases' => $columnAliases
        ];

        // Ana sorgu için dönecek sütun listesi
        $result['where_columns'] = $uniqueColumns;

        // Tüm tablolar için indeks önerileri oluştur
        $this->analyzeTablesForSuggestions($result);

        return $result;
    }

    /**
     * SQL sorgusunu normalleştir
     *
     * @param string $sql
     * @return string
     */
    protected function normalizeSql(string $sql): string
    {
        $sql = trim($sql);

        // Çok satırlı yorumları temizle (/* ... */)
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);

        // Tek satırlı yorumları temizle (-- ... ve # ...)
        $sql = preg_replace('/--.*?\n|#.*?\n/', '\n', $sql);

        // Fazladan boşlukları temizle ve tek satır haline getir
        $sql = preg_replace('/\s+/', ' ', $sql);

        return $sql;
    }

    /**
     * SQL sorgusundan FROM tablosunu çıkarır
     *
     * @param string $sql
     * @return array|null
     */
    protected function extractFromTable(string $sql): ?array
    {
        // Basit FROM ifadesi: FROM table [AS] alias
        $basicFrom = '/\bFROM\s+`?([a-zA-Z0-9_]+)`?(?:\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?)?/i';

        // Alt sorgu FROM ifadesi: FROM (SELECT...) [AS] alias
        $subqueryFrom = '/\bFROM\s+\(([^()]+(?:\([^()]*\)[^()]*)*?)\)\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?/is';

        // Önce alt sorguları kontrol et
        if (preg_match($subqueryFrom, $sql, $match)) {
            return [
                'table' => 'subquery_' . md5($match[1]), // Alt sorgular için benzersiz bir isim oluştur
                'alias' => $match[2],
                'is_subquery' => true
            ];
        }

        // Basit tablo adları için kontrol et
        if (preg_match($basicFrom, $sql, $match)) {
            return [
                'table' => $match[1],
                'alias' => !empty($match[2]) ? $match[2] : $match[1],
                'is_subquery' => false
            ];
        }

        return null;
    }

    /**
     * SQL sorgusundan UPDATE tablosunu çıkarır
     *
     * @param string $sql
     * @return array|null
     */
    protected function extractUpdateTable(string $sql): ?array
    {
        if (preg_match('/UPDATE\s+`?([a-zA-Z0-9_]+)`?(?:\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?)?/i', $sql, $match)) {
            return [
                'table' => $match[1],
                'alias' => !empty($match[2]) ? $match[2] : $match[1],
                'is_subquery' => false
            ];
        }

        return null;
    }

    /**
     * SQL sorgusundan DELETE tablosunu çıkarır
     *
     * @param string $sql
     * @return array|null
     */
    protected function extractDeleteTable(string $sql): ?array
    {
        if (preg_match('/DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?(?:\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?)?/i', $sql, $match)) {
            return [
                'table' => $match[1],
                'alias' => !empty($match[2]) ? $match[2] : $match[1],
                'is_subquery' => false
            ];
        }

        return null;
    }

    /**
     * SQL sorgusundan JOIN tablolarını çıkarır
     *
     * @param string $sql
     * @return array
     */
    protected function extractJoinTables(string $sql): array
    {
        $joinTables = [];

        // Basit JOIN ifadesi: JOIN table [AS] alias ON condition
        $basicJoin = '/\b(INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+`?([a-zA-Z0-9_]+)`?(?:\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?)?\s+ON\s+(.+?)(?=\s+(?:INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+|\s+WHERE\s+|\s+GROUP\s+BY|\s+ORDER\s+BY|\s+LIMIT|$)/is';

        // Alt sorgu JOIN ifadesi: JOIN (SELECT...) [AS] alias ON condition
        $subqueryJoin = '/\b(INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+\(([^()]+(?:\([^()]*\)[^()]*)*?)\)\s+(?:AS\s+)?`?([a-zA-Z0-9_]+)`?\s+ON\s+(.+?)(?=\s+(?:INNER|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+|\s+WHERE\s+|\s+GROUP\s+BY|\s+ORDER\s+BY|\s+LIMIT|$)/is';

        // Önce alt sorgu JOIN'leri bul
        preg_match_all($subqueryJoin, $sql, $subqueryJoinMatches, PREG_SET_ORDER);

        foreach ($subqueryJoinMatches as $match) {
            $joinType = $match[1] ?: 'INNER';
            $joinSubquery = $match[2];
            $joinAlias = $match[3];
            $joinCondition = $match[4];

            $joinTables[] = [
                'type' => $joinType,
                'table' => 'subquery_' . md5($joinSubquery), // Alt sorgular için benzersiz isim
                'alias' => $joinAlias,
                'condition' => $joinCondition,
                'is_subquery' => true
            ];
        }

        // Şimdi normal JOIN'leri bul
        preg_match_all($basicJoin, $sql, $basicJoinMatches, PREG_SET_ORDER);

        foreach ($basicJoinMatches as $match) {
            $joinType = $match[1] ?: 'INNER';
            $joinTable = $match[2];
            $joinAlias = !empty($match[3]) ? $match[3] : $joinTable;
            $joinCondition = $match[4];

            $joinTables[] = [
                'type' => $joinType,
                'table' => $joinTable,
                'alias' => $joinAlias,
                'condition' => $joinCondition,
                'is_subquery' => false
            ];
        }

        return $joinTables;
    }

    /**
     * SQL sorgusundan WHERE cümlesini çıkarır
     *
     * @param string $sql
     * @return string|null
     */
    protected function extractWhereClause(string $sql): ?string
    {
        if (preg_match('/\bWHERE\s+(.+?)(?:\s+GROUP\s+BY|\s+HAVING|\s+ORDER\s+BY|\s+LIMIT|\s+UNION|\s+EXCEPT|\s+INTERSECT|$)/is', $sql, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * SQL sorgusundan HAVING cümlesini çıkarır
     *
     * @param string $sql
     * @return string|null
     */
    protected function extractHavingClause(string $sql): ?string
    {
        if (preg_match('/\bHAVING\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|\s+UNION|\s+EXCEPT|\s+INTERSECT|$)/is', $sql, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * SQL sorgusundan GROUP BY cümlesini çıkarır
     *
     * @param string $sql
     * @return string|null
     */
    protected function extractGroupByClause(string $sql): ?string
    {
        if (preg_match('/\bGROUP\s+BY\s+(.+?)(?:\s+HAVING|\s+ORDER\s+BY|\s+LIMIT|\s+UNION|\s+EXCEPT|\s+INTERSECT|$)/is', $sql, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * SQL sorgusundan ORDER BY cümlesini çıkarır
     *
     * @param string $sql
     * @return string|null
     */
    protected function extractOrderByClause(string $sql): ?string
    {
        if (preg_match('/\bORDER\s+BY\s+(.+?)(?:\s+LIMIT|\s+UNION|\s+EXCEPT|\s+INTERSECT|$)/is', $sql, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * SQL sorgusundan SELECT sütunlarını çıkarır
     *
     * @param string $sql
     * @param array &$selectColumns
     * @param array &$columnAliases
     * @return void
     */
    protected function extractSelectColumns(string $sql, array &$selectColumns, array &$columnAliases): void
    {
        // SELECT ile FROM arasındaki kısmı al
        if (!preg_match('/SELECT\s+(.+?)\s+FROM/is', $sql, $match)) {
            return;
        }

        $selectClause = $match[1];

        // Parantez eşleştirmesini doğru yapabilmek için biraz daha karmaşık bir mantık gerekli
        // Virgülle ayrılmış sütunları bölmek için state-machine kullanacağız
        $columns = [];
        $currentColumn = '';
        $parenLevel = 0;

        for ($i = 0; $i < strlen($selectClause); $i++) {
            $char = $selectClause[$i];

            if ($char === '(') {
                $parenLevel++;
                $currentColumn .= $char;
            } elseif ($char === ')') {
                $parenLevel--;
                $currentColumn .= $char;
            } elseif ($char === ',' && $parenLevel === 0) {
                // Parantez dışındayken virgül bulduğumuzda, sütunu listeye ekle
                $columns[] = trim($currentColumn);
                $currentColumn = '';
            } else {
                $currentColumn .= $char;
            }
        }

        // Son sütunu ekle
        if (trim($currentColumn) !== '') {
            $columns[] = trim($currentColumn);
        }

        // Şimdi her sütunu ayrıştır
        foreach ($columns as $column) {
            // AS ile alias belirtilmiş durumlar: column AS alias
            if (preg_match('/^(.+?)\s+AS\s+`?([a-zA-Z0-9_]+)`?$/i', $column, $aliasMatch)) {
                $expr = $aliasMatch[1];
                $alias = $aliasMatch[2];

                // İfade içindeki sütunları çıkar
                $this->extractColumnsFromExpression($expr, $selectColumns);

                // Alias'ı kaydet
                $columnAliases[$alias] = $expr;
            } // Alias belirtilmemiş table.column durumları
            elseif (preg_match('/^`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?$/i', $column, $tableColMatch)) {
                $table = $tableColMatch[1];
                $col = $tableColMatch[2];

                $selectColumns[] = [
                    'table' => $table,
                    'column' => $col,
                    'alias' => $table
                ];
            } // Örtük alias: expr alias
            elseif (preg_match('/^(.+?)\s+`?([a-zA-Z0-9_]+)`?$/i', $column, $implicitAliasMatch)) {
                $expr = $implicitAliasMatch[1];
                $alias = $implicitAliasMatch[2];

                // İfade içindeki sütunları çıkar
                $this->extractColumnsFromExpression($expr, $selectColumns);

                // Alias'ı kaydet
                $columnAliases[$alias] = $expr;
            } // * durumu (tüm sütunlar)
            elseif ($column === '*') {
                // Tüm sütunlar seçildiğinde özel bir işlem yapmaya gerek yok
                continue;
            } // table.* durumu (bir tablonun tüm sütunları)
            elseif (preg_match('/^`?([a-zA-Z0-9_]+)`?\.\*$/i', $column, $tableStarMatch)) {
                $table = $tableStarMatch[1];
                // Bir tablonun tüm sütunları seçildiğinde özel bir işlem yapmaya gerek yok
                continue;
            } // CASE, IF, COALESCE, IFNULL gibi SQL fonksiyonları
            else {
                // İfade içindeki sütunları çıkar
                $this->extractColumnsFromExpression($column, $selectColumns);
            }
        }
    }

    /**
     * Bir SQL ifadesinden sütunları çıkarır
     *
     * @param string $expr
     * @param array &$columns
     * @return void
     */
    protected function extractColumnsFromExpression(string $expr, array &$columns): void
    {
        // table.column formatındaki tüm sütunları bul
        preg_match_all('/`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?/i', $expr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $table = $match[1];
            $column = $match[2];

            $columns[] = [
                'table' => $table,
                'column' => $column,
                'alias' => $table
            ];
        }
    }

    /**
     * CASE/WHEN/IF ifadelerini analiz eder
     *
     * @param string $sql
     * @param array $tables
     * @param array &$caseWhenColumns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function analyzeCaseWhenStatements(string $sql, array $tables, array &$caseWhenColumns, string $mainTableAlias, string $mainTable): void
    {
        // CASE WHEN ... THEN ... END yapıları
        preg_match_all('/CASE\s+WHEN\s+(.+?)\s+THEN\s+.+?\s+END/is', $sql, $caseMatches, PREG_PATTERN_ORDER);

        foreach ($caseMatches[1] as $whenCondition) {
            // WHEN koşulundaki sütunları bul
            $this->extractColumnsFromLogicalExpression($whenCondition, $tables, $caseWhenColumns, $mainTableAlias, $mainTable);
        }

        // IF(condition, true_result, false_result) yapıları
        preg_match_all('/IF\s*\((.+?),/is', $sql, $ifMatches, PREG_PATTERN_ORDER);

        foreach ($ifMatches[1] as $ifCondition) {
            // IF koşulundaki sütunları bul
            $this->extractColumnsFromLogicalExpression($ifCondition, $tables, $caseWhenColumns, $mainTableAlias, $mainTable);
        }
    }

    /**
     * JOIN koşullarını analiz eder
     *
     * @param string $joinCondition
     * @param array $tables
     * @param array &$joinColumns
     * @return void
     */
    protected function analyzeJoinCondition(string $joinCondition, array $tables, array &$joinColumns): void
    {
        // table.column = table.column formatındaki koşulları bul
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?)\s*=\s*(`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?)/i', $joinCondition, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $leftAlias = $match[2];
            $leftColumn = $match[3];
            $rightAlias = $match[5];
            $rightColumn = $match[6];

            // Sol tarafın tablosunu bul
            if (isset($tables[$leftAlias])) {
                $leftTable = $tables[$leftAlias];
                $joinColumns[] = [
                    'table' => $leftTable,
                    'column' => $leftColumn,
                    'alias' => $leftAlias,
                ];
            }

            // Sağ tarafın tablosunu bul
            if (isset($tables[$rightAlias])) {
                $rightTable = $tables[$rightAlias];
                $joinColumns[] = [
                    'table' => $rightTable,
                    'column' => $rightColumn,
                    'alias' => $rightAlias,
                ];
            }
        }

        // AND ile bağlanan birden fazla koşul için ayrıştırma
        $andConditions = preg_split('/\s+AND\s+/i', $joinCondition);

        foreach ($andConditions as $condition) {
            // USING(column) formatındaki koşullar
            if (preg_match('/USING\s*\(\s*`?([a-zA-Z0-9_,\s]+)`?\s*\)/i', $condition, $usingMatch)) {
                $usingColumns = explode(',', $usingMatch[1]);

                foreach ($usingColumns as $column) {
                    $column = trim($column);

                    // USING içindeki her sütun için tüm tablolarda indeks öner
                    foreach ($tables as $alias => $tableName) {
                        $joinColumns[] = [
                            'table' => $tableName,
                            'column' => $column,
                            'alias' => $alias,
                        ];
                    }
                }
            }
        }
    }

    /**
     * WHERE koşullarını analiz eder
     *
     * @param string $whereClause
     * @param array $tables
     * @param array &$whereColumns
     * @param array &$inClauseColumns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function analyzeWhereClause(string $whereClause, array $tables, array &$whereColumns, array &$inClauseColumns, string $mainTableAlias, string $mainTable): void
    {
        // WHERE 1=1 gibi sabit koşulları atla
        if (preg_match('/^\s*\d+\s*=\s*\d+\s*$/i', $whereClause)) {
            return;
        }

        // Mantıksal ifadeleri ayrıştır (AND, OR gruplarını dikkate al)
        $this->extractColumnsFromLogicalExpression($whereClause, $tables, $whereColumns, $mainTableAlias, $mainTable);

        // IN cümlelerini özel olarak işle
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?\s+IN\s+\([^)]+\)/i', $whereClause, $inMatches, PREG_SET_ORDER);

        foreach ($inMatches as $match) {
            $alias = !empty($match[2]) ? $match[2] : $mainTableAlias;
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $inClauseColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            } else {
                // Alias belirtilmemişse, ana tabloyu kullan
                $inClauseColumns[] = [
                    'table' => $mainTable,
                    'column' => $column,
                    'alias' => $mainTableAlias,
                ];
            }
        }

        // NOT IN cümlelerini de işle
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?\s+NOT\s+IN\s+\([^)]+\)/i', $whereClause, $notInMatches, PREG_SET_ORDER);

        foreach ($notInMatches as $match) {
            $alias = !empty($match[2]) ? $match[2] : $mainTableAlias;
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $inClauseColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            } else {
                // Alias belirtilmemişse, ana tabloyu kullan
                $inClauseColumns[] = [
                    'table' => $mainTable,
                    'column' => $column,
                    'alias' => $mainTableAlias,
                ];
            }
        }

        // IS NULL / IS NOT NULL yapılarını işle
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?\s+IS\s+(?:NOT\s+)?NULL/i', $whereClause, $isNullMatches, PREG_SET_ORDER);

        foreach ($isNullMatches as $match) {
            $alias = !empty($match[2]) ? $match[2] : $mainTableAlias;
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $whereColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            } else {
                // Alias belirtilmemişse, ana tabloyu kullan
                $whereColumns[] = [
                    'table' => $mainTable,
                    'column' => $column,
                    'alias' => $mainTableAlias,
                ];
            }
        }

        // BETWEEN yapılarını işle
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?\s+BETWEEN\s+.+?\s+AND\s+.+?(?=[\s,)]|$)/i', $whereClause, $betweenMatches, PREG_SET_ORDER);

        foreach ($betweenMatches as $match) {
            $alias = !empty($match[2]) ? $match[2] : $mainTableAlias;
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $whereColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            } else {
                // Alias belirtilmemişse, ana tabloyu kullan
                $whereColumns[] = [
                    'table' => $mainTable,
                    'column' => $column,
                    'alias' => $mainTableAlias,
                ];
            }
        }
    }

    /**
     * Mantıksal ifadelerden sütunları çıkarır (AND/OR gruplarını dikkate alarak)
     *
     * @param string $expression
     * @param array $tables
     * @param array &$columns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function extractColumnsFromLogicalExpression(string $expression, array $tables, array &$columns, string $mainTableAlias, string $mainTable): void
    {
        // table.column operator value biçimindeki koşulları işle
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?)\s*(?:=|>|<|>=|<=|<>|!=|LIKE|IN|IS|NOT IN|NOT|BETWEEN)/i', $expression, $tableColumnMatches, PREG_SET_ORDER);

        foreach ($tableColumnMatches as $match) {
            $alias = $match[2];
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $columns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            }
        }

        // Alias olmadan doğrudan column adı olan koşulları işle
        preg_match_all('/(?<![\.a-zA-Z0-9_])\s*`?([a-zA-Z0-9_]+)`?\s*(?:=|>|<|>=|<=|<>|!=|LIKE|IN|IS|NOT IN|NOT|BETWEEN)/i', $expression, $columnOnlyMatches);

        if (!empty($columnOnlyMatches[1])) {
            foreach ($columnOnlyMatches[1] as $column) {
                // SQL anahtar kelimeleri hariç tut
                if (!in_array(strtoupper($column), ['AND', 'OR', 'NULL', 'NOT', 'IS', 'IN', 'LIKE', 'BETWEEN', 'TRUE', 'FALSE', 'UNKNOWN'])) {
                    $columns[] = [
                        'table' => $mainTable,
                        'column' => $column,
                        'alias' => $mainTableAlias,
                    ];
                }
            }
        }
    }

    /**
     * HAVING koşullarını analiz eder
     *
     * @param string $havingClause
     * @param array $tables
     * @param array &$havingColumns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function analyzeHavingClause(string $havingClause, array $tables, array &$havingColumns, string $mainTableAlias, string $mainTable): void
    {
        // HAVING cümlesi genellikle grup fonksiyonları içerir, ancak yine de temel sütun adları önemlidir
        $this->extractColumnsFromLogicalExpression($havingClause, $tables, $havingColumns, $mainTableAlias, $mainTable);

        // Agregat fonksiyonlar içindeki sütunları da bul
        preg_match_all('/(COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(?:DISTINCT\s+)?(?:`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?\s*\)/i', $havingClause, $aggregateMatches, PREG_SET_ORDER);

        foreach ($aggregateMatches as $match) {
            $alias = !empty($match[2]) ? $match[2] : $mainTableAlias;
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $havingColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            } else {
                // Alias belirtilmemişse, ana tabloyu kullan
                $havingColumns[] = [
                    'table' => $mainTable,
                    'column' => $column,
                    'alias' => $mainTableAlias,
                ];
            }
        }
    }

    /**
     * GROUP BY ifadelerini analiz eder
     *
     * @param string $groupByClause
     * @param array $tables
     * @param array &$groupByColumns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function analyzeGroupByClause(string $groupByClause, array $tables, array &$groupByColumns, string $mainTableAlias, string $mainTable): void
    {
        // table.column formatındaki GROUP BY öğelerini bul
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?)/i', $groupByClause, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $alias = $match[2];
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $groupByColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            }
        }

        // Sadece column adı olan GROUP BY öğeleri için
        preg_match_all('/(?<![\.a-zA-Z0-9_])\s*`?([a-zA-Z0-9_]+)`?\s*(?:,|$)/i', $groupByClause, $columnOnlyMatches);

        if (!empty($columnOnlyMatches[1])) {
            foreach ($columnOnlyMatches[1] as $column) {
                // Rakamları atla (pozisyonel GROUP BY için)
                if (!is_numeric($column)) {
                    $groupByColumns[] = [
                        'table' => $mainTable,
                        'column' => $column,
                        'alias' => $mainTableAlias,
                    ];
                }
            }
        }
    }

    /**
     * ORDER BY ifadelerini analiz eder
     *
     * @param string $orderByClause
     * @param array $tables
     * @param array &$orderByColumns
     * @param string $mainTableAlias
     * @param string $mainTable
     * @return void
     */
    protected function analyzeOrderByClause(string $orderByClause, array $tables, array &$orderByColumns, string $mainTableAlias, string $mainTable): void
    {
        // table.column formatındaki ORDER BY öğelerini bul
        preg_match_all('/(`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?)(?:\s+(?:ASC|DESC))?/i', $orderByClause, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $alias = $match[2];
            $column = $match[3];

            if (isset($tables[$alias])) {
                $table = $tables[$alias];
                $orderByColumns[] = [
                    'table' => $table,
                    'column' => $column,
                    'alias' => $alias,
                ];
            }
        }

        // Sadece column adı olan ORDER BY öğeleri için
        preg_match_all('/(?<![\.a-zA-Z0-9_])\s*`?([a-zA-Z0-9_]+)`?(?:\s+(?:ASC|DESC))?\s*(?:,|$)/i', $orderByClause, $columnOnlyMatches);

        if (!empty($columnOnlyMatches[1])) {
            foreach ($columnOnlyMatches[1] as $column) {
                // Rakamları atla (pozisyonel ORDER BY için)
                if (!is_numeric($column)) {
                    $orderByColumns[] = [
                        'table' => $mainTable,
                        'column' => $column,
                        'alias' => $mainTableAlias,
                    ];
                }
            }
        }
    }

    /**
     * Alt sorguları (subquery) çıkar ve ayrıştır
     *
     * @param string $sql
     * @return void
     */
    protected function extractAndParseSubqueries(string $sql): void
    {
        // FROM ifadesi içindeki alt sorguları bul (örn. FROM (SELECT ...) AS subquery)
        preg_match_all('/\bFROM\s+\(([^()]+(?:\([^()]*\)[^()]*)*?)\)\s+(?:AS\s+)?([a-zA-Z0-9_]+)/is', $sql, $fromSubqueries, PREG_SET_ORDER);

        foreach ($fromSubqueries as $match) {
            $subquery = $match[1];
            $alias = $match[2];

            // Alt sorguyu analiz et
            $this->parseQuery($subquery);
        }

        // JOIN ifadesi içindeki alt sorguları bul
        preg_match_all('/\bJOIN\s+\(([^()]+(?:\([^()]*\)[^()]*)*?)\)\s+(?:AS\s+)?([a-zA-Z0-9_]+)/is', $sql, $joinSubqueries, PREG_SET_ORDER);

        foreach ($joinSubqueries as $match) {
            $subquery = $match[1];
            $alias = $match[2];

            // Alt sorguyu analiz et
            $this->parseQuery($subquery);
        }

        // WHERE koşulundaki alt sorguları bul (IN, EXISTS, vb.)
        preg_match_all('/\b(?:IN|EXISTS)\s*\(([^()]+(?:\([^()]*\)[^()]*)*?)\)/is', $sql, $whereSubqueries, PREG_SET_ORDER);

        foreach ($whereSubqueries as $match) {
            $subquery = $match[1];

            // Eğer bu bir SELECT ifadesi ise
            if (preg_match('/^\s*SELECT/i', $subquery)) {
                // Alt sorguyu analiz et
                $this->parseQuery($subquery);
            }
        }

        // SELECT içindeki alt sorguları bul
        preg_match_all('/\bSELECT\s+.+?\bFROM\b.+?\b(WHERE|GROUP|ORDER|LIMIT|$)/is', $sql, $selectSubqueries, PREG_SET_ORDER);

        foreach ($selectSubqueries as $selectMatch) {
            $selectSubquery = $selectMatch[0];

            // Parantez içindeki alt sorguları bul
            preg_match_all('/\(\s*(SELECT\s+.+?)\s*\)/is', $selectSubquery, $nestedSubqueries, PREG_SET_ORDER);

            foreach ($nestedSubqueries as $nestedMatch) {
                $nestedSubquery = $nestedMatch[1];
                // Alt sorguyu analiz et
                $this->parseQuery($nestedSubquery);
            }
        }

        // WITH (CTE) ifadelerini bul
        preg_match_all('/\bWITH\s+([a-zA-Z0-9_]+)\s+AS\s+\(([^()]+(?:\([^()]*\)[^()]*)*?)\)/is', $sql, $cteSubqueries, PREG_SET_ORDER);

        foreach ($cteSubqueries as $match) {
            $cteName = $match[1];
            $subquery = $match[2];

            // CTE alt sorgusunu analiz et
            $this->parseQuery($subquery);
        }
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
            // Alt sorgu tabloları için öneri oluşturma
            if (isset($queryData['subquery_tables']) && in_array($tableName, $queryData['subquery_tables'])) {
                continue;
            }

            if (!isset($this->tableSuggestions[$tableName])) {
                $this->tableSuggestions[$tableName] = [];
            }

            // JOIN sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['join_columns'])) {
                foreach ($queryData['join_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // WHERE sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['where_columns'])) {
                foreach ($queryData['where_columns'] as $col) {
                    // Dizi veya string olarak gelmiş olabilir
                    if (is_array($col) && $col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    } elseif (is_string($col) && $tableName === $queryData['table'] && !in_array($col, $this->tableSuggestions[$tableName])) {
                        // Ana tablo için string olarak gelmiş olabilir
                        $this->tableSuggestions[$tableName][] = $col;
                    }
                }
            }

            // ORDER BY sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['order_by_columns'])) {
                foreach ($queryData['order_by_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // GROUP BY sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['group_by_columns'])) {
                foreach ($queryData['group_by_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // HAVING sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['having_columns'])) {
                foreach ($queryData['having_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // IN clause sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['in_clause_columns'])) {
                foreach ($queryData['in_clause_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // CASE/WHEN/IF sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['case_when_columns'])) {
                foreach ($queryData['case_when_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
                }
            }

            // SELECT sütunları - Bu tabloya ait olanları ekle
            if (isset($queryData['select_columns'])) {
                foreach ($queryData['select_columns'] as $col) {
                    if ($col['table'] === $tableName && !in_array($col['column'], $this->tableSuggestions[$tableName])) {
                        $this->tableSuggestions[$tableName][] = $col['column'];
                    }
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
