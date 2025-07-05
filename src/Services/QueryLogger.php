<?php

namespace SekizliPenguen\IndexAnalyzer\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class QueryLogger
{
    /**
     * Uygulama örneği.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Depolanan sorgular.
     *
     * @var array
     */
    protected array $queries = [];

    /**
     * Yeni bir sorgu günlükleyici örneği oluştur.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * SQL sorgusunu günlüğe kaydet.
     *
     * @param QueryExecuted $query
     * @return void
     */
    public function logQuery(QueryExecuted $query)
    {
        // Belirli sorgu türlerini yoksay
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

        // Hash'e göre sadece benzersiz sorguları kaydet
        if (!$this->isDuplicateQuery($queryData['hash'])) {
            $this->storeQuery($queryData);
        }
    }

    /**
     * Bir sorgunun yoksayılması gerekip gerekmediğini kontrol et.
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

        // Yoksayma desenlerini kontrol et
        foreach ($ignoredPatterns as $pattern) {
            if (Str::contains(strtoupper($sql), strtoupper($pattern))) {
                return true;
            }
        }

        // Yoksayılan tabloları kontrol et
        foreach ($ignoredTables as $table) {
            $pattern = "FROM `?{$table}`?";
            if (preg_match("/{$pattern}/i", $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * SQL sorgusunu biçimlendir.
     *
     * @param string $sql
     * @return string
     */
    protected function formatSql($sql)
    {
        return $sql;
    }

    /**
     * Sorgu için benzersiz bir hash oluştur.
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
     * Verilen hash'e sahip sorgunun zaten kaydedilip kaydedilmediğini kontrol et.
     *
     * @param string $hash
     * @return bool
     */
    protected function isDuplicateQuery(string $hash): bool
    {
        // Bellekteki sorguları kontrol et
        foreach ($this->queries as $query) {
            if (isset($query['debug_hash']) && $query['debug_hash'] === $hash) {
                return true;
            }
        }

        // Dosyada da kontrol et (eğer dosya depolama kullanılıyorsa)
        if (config('index-analyzer.storage') === 'file') {
            // Önbellek dosyası oluştur/kontrol et
            $hashCachePath = $this->getHashCachePath();

            // Hash'i önbellekte ara
            if (File::exists($hashCachePath)) {
                $hashCache = json_decode(File::get($hashCachePath), true) ?: [];
                if (in_array($hash, $hashCache)) {
                    return true;
                }
            }

            // Log dosyasında ara (önbellekte yoksa)
            $logPath = config('index-analyzer.log_path', storage_path('logs/index-analyzer.log'));

            if (File::exists($logPath)) {
                try {
                    // Dosya çok büyükse sadece son 50 satırını kontrol et
                    $filesize = filesize($logPath);
                    $checkLines = ($filesize > 50000) ? 50 : 200; // 50KB'dan büyükse sınırla

                    $lines = $this->getTailOfFile($logPath, $checkLines);
                    foreach ($lines as $line) {
                        $decoded = json_decode($line, true);
                        if ($decoded && isset($decoded['debug_hash']) && $decoded['debug_hash'] === $hash) {
                            // Hash'i önbelleğe ekle
                            $this->addHashToCache($hash);
                            return true;
                        }
                    }
                } catch (\Exception $e) {
                    // Hata durumunda sessizce devam et
                }
            }
        }

        return false;
    }

    /**
     * Hash önbellek dosyasının yolunu al
     *
     * @return string
     */
    protected function getHashCachePath()
    {
        $directory = storage_path('framework/cache');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory . '/index-analyzer-hashes.json';
    }

    /**
     * Önbellek dosyasına bir hash ekle
     *
     * @param string $hash
     * @return void
     */
    protected function addHashToCache($hash)
    {
        $cachePath = $this->getHashCachePath();
        $hashCache = [];

        if (File::exists($cachePath)) {
            $hashCache = json_decode(File::get($cachePath), true) ?: [];
        }

        // Hash'i ekle ve maksimum 1000 hash tut
        $hashCache[] = $hash;
        if (count($hashCache) > 1000) {
            $hashCache = array_slice($hashCache, -1000);
        }

        File::put($cachePath, json_encode($hashCache));
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
     * Sorgu verilerini depola.
     *
     * @param array $queryData
     * @return void
     */
    protected function storeQuery(array $queryData)
    {
        // Orijinal hash'i hata ayıklama amacıyla sakla
        $queryData['debug_hash'] = $queryData['hash'];

        // Her sorguyu benzersiz yapmak için zaman damgası ekle
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

        // Sorguyu bellekte tut
        $this->queries[] = $queryData;

        // Konfigürasyona göre dosyaya kaydet
        if (config('index-analyzer.storage') === 'file') {
            $this->storeToFile($queryData);
        }
    }

    /**
     * Sorgu verilerini bir dosyaya kaydet.
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

        // Çok yakın zamanda aynı hash'li sorguyu kaydetme
        if (isset($queryData['debug_hash'])) {
            $hash = $queryData['debug_hash'];
            $this->addHashToCache($hash);
        }

        try {
            // Dizin kontrolü yap ve dizin yoksa oluştur
            $directory = dirname($logPath);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Dosya çok büyükse, yeni dosya oluştur
            $maxSize = config('index-analyzer.max_log_size', 10 * 1024 * 1024); // Varsayılan 10MB
            if (File::exists($logPath) && filesize($logPath) > $maxSize) {
                // Eski dosyayı rotasyon yap
                $timestamp = date('Y-m-d-His');
                $backupPath = str_replace('.log', "-{$timestamp}.log", $logPath);
                File::move($logPath, $backupPath);

                // Eski dosyaları temizle (en son 5 dosyayı tut)
                $this->cleanupOldLogFiles($logPath, 5);
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
     * Eski log dosyalarını temizle
     *
     * @param string $basePath
     * @param int $keepCount
     * @return void
     */
    protected function cleanupOldLogFiles($basePath, $keepCount = 5)
    {
        $directory = dirname($basePath);
        $filename = basename($basePath, '.log');

        // Bu log dosyasına ait tüm eski sürümleri bul
        $pattern = $directory . '/' . $filename . '-*.log';
        $files = glob($pattern);

        // Tarihe göre sırala (en yeni önce)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Sadece en yeni $keepCount kadar dosyayı tut, diğerlerini sil
        if (count($files) > $keepCount) {
            $filesToDelete = array_slice($files, $keepCount);
            foreach ($filesToDelete as $file) {
                File::delete($file);
            }
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
            // Log dosyasını temizle
            $logPath = config('index-analyzer.log_path');
            if (File::exists($logPath)) {
                File::put($logPath, '');
            }

            // Hash önbelleğini de temizle
            $hashCachePath = $this->getHashCachePath();
            if (File::exists($hashCachePath)) {
                File::delete($hashCachePath);
            }
        }
    }
}
