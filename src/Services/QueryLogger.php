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
     * @var Application
     */
    protected Application $app;

    /**
     * Stored queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Create a new query logger instance.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Log a SQL query.
     *
     * @param QueryExecuted $query
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
        // Sistem tablolarını ve diğer özel sorguları yoksay
        $ignoredPatterns = [
            'INFORMATION_SCHEMA',
            'pg_catalog',
            'sqlite_master',
            'SHOW TABLES',
            'SHOW COLUMNS',
            'SHOW FULL TABLES',
            'SHOW TABLE STATUS',
            'SHOW CREATE TABLE',
            'SHOW VARIABLES',
            'PRAGMA',
            'SET NAMES',
            'SET CHARACTER SET',
            'SET TIME_ZONE',
            'SET SESSION',
        ];

        // Bu tabloları da yoksay
        $ignoredTables = config('index-analyzer.ignored_tables', [
            'migrations',
            'jobs',
            'failed_jobs',
            'password_resets',
            'sessions',
            'personal_access_tokens',
        ]);

        // İgnore pattern'leri kontrol et
        foreach ($ignoredPatterns as $pattern) {
            if (Str::contains(strtoupper($sql), strtoupper($pattern))) {
                return true;
            }
        }

        // İgnore tabloları kontrol et
        foreach ($ignoredTables as $table) {
            $pattern = "FROM `?{$table}`?";
            if (preg_match("/{$pattern}/i", $sql)) {
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
     * @param QueryExecuted $query
     * @return string
     */
    protected function generateQueryHash(QueryExecuted $query)
    {
        // Sadece SQL yapısını hash'le, parametreleri dikkate alma
        // Bu, aynı sorgunun farklı parametre değerleriyle tekrar kaydedilmesini önler
        return md5($query->sql);
    }

    /**
     * Check if the query with the given hash has already been logged.
     *
     * @param string $hash
     * @return bool
     */
    protected function isDuplicateQuery($hash)
    {
        // Memory'deki sorguları kontrol et
        foreach ($this->queries as $query) {
            if (isset($query['debug_hash']) && $query['debug_hash'] === $hash) {
                return true;
            }
        }

        // Dosyada da kontrol et (eğer dosya depolama kullanılıyorsa)
        if (config('index-analyzer.storage') === 'file') {
            $logPath = config('index-analyzer.log_path', storage_path('logs/index-analyzer.log'));

            if (File::exists($logPath)) {
                try {
                    // Dosyanın son 10 satırını kontrol et - performans için sınırlı tutuyoruz
                    $lines = $this->getTailOfFile($logPath, 10);
                    foreach ($lines as $line) {
                        $decoded = json_decode($line, true);
                        if ($decoded && isset($decoded['debug_hash']) && $decoded['debug_hash'] === $hash) {
                            return true;
                        }
                    }
                } catch (\Exception $e) {
                    // Hata durumunda sadece devam et
                }
            }
        }

        return false;
    }

    /**
     * Dosyanın son N satırını getir
     *
     * @param string $filepath
     * @param int $lines
     * @return array
     */
    protected function getTailOfFile($filepath, $lines = 10)
    {
        $result = [];

        if (!file_exists($filepath)) {
            return $result;
        }

        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX); // Son satıra git
        $lastLine = $file->key();

        $startLine = max(0, $lastLine - $lines);

        for ($i = $startLine; $i <= $lastLine; $i++) {
            $file->seek($i);
            $line = $file->current();
            if (trim($line) !== '') {
                $result[] = trim($line);
            }
        }

        return $result;
    }

    /**
     * Store the query data.
     *
     * @param array $queryData
     * @return void
     */
    protected function storeQuery(array $queryData)
    {
        // Orijinal hash'i debug amacıyla sakla
        $queryData['debug_hash'] = $queryData['hash'];

        // Her sorguyu benzersiz yapmak için timestamp ekle
        $queryData['hash'] = $queryData['hash'] . '_' . microtime(true);

        // Gereksiz/hassas veri temizleme
        if (isset($queryData['bindings']) && is_array($queryData['bindings'])) {
            // Sadece veri tiplerini sakla, gerçek değerleri değil
            $queryData['bindings'] = array_map(function ($binding) {
                if (is_string($binding)) {
                    return '[string]';
                } elseif (is_numeric($binding)) {
                    return '[numeric]';
                } elseif (is_bool($binding)) {
                    return '[boolean]';
                } elseif (is_null($binding)) {
                    return '[null]';
                } else {
                    return '[other]';
                }
            }, $queryData['bindings']);
        }

        // Sorguyu hafızada tut
        $this->queries[] = $queryData;

        // Konfigürasyona göre dosyaya kaydet
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
        $logPath = config('index-analyzer.log_path', storage_path('logs/index-analyzer.log'));

        if (empty($logPath)) {
            return;
        }

        try {
            // Dizin kontrolü yap ve dizin yoksa oluştur
            $directory = dirname($logPath);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // JSON verisini oluştur
            $jsonData = json_encode($queryData) . PHP_EOL;

            // Dosyaya ekle
            File::append($logPath, $jsonData);
        } catch (\Exception $e) {
            // Hata durumunda sessizce devam et
        }
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
        $logPath = config('index-analyzer.log_path', storage_path('logs/index-analyzer.log'));

        if (empty($logPath) || !File::exists($logPath)) {
            return [];
        }

        $queries = [];

        try {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $decoded = json_decode($line, true);
                if ($decoded) {
                    $queries[] = $decoded;
                }
            }
        } catch (\Exception $e) {
            // Dosya okuma hatası durumunda boş dizi döndür
            return [];
        }

        return $queries;
    }

    /**
     * Clear all stored queries.
     *
     * @return void
     */
    public function clearQueries(): void
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
