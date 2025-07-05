<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IndexAnalyzer Aktif/Pasif Durumu
    |--------------------------------------------------------------------------
    |
    | Bu paketi etkinleştirmek veya devre dışı bırakmak için bu ayarı kullanın.
    |
    */
    'enabled' => env('INDEX_ANALYZER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Rota Öneki
    |--------------------------------------------------------------------------
    |
    | Paket tarafından sağlanan rotaların öneki.
    |
    */
    'route_prefix' => 'index-analyzer',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | IndexAnalyzer rotaları için kullanılacak middleware'ler.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Debug Bar Ayarları
    |--------------------------------------------------------------------------
    |
    | Ekranın altında görünen DebugBar'ın görünümünü özelleştirin.
    |
    */
    'debug_bar' => [
        'position' => 'bottom', // 'top' veya 'bottom'
        'theme' => 'light',     // 'light' veya 'dark'
        'auto_show' => true,    // Otomatik göster veya gizle
    ],

    /*
    |--------------------------------------------------------------------------
    | Depolama Ayarları
    |--------------------------------------------------------------------------
    |
    | Sorgu verilerinin nerede saklanacağı.
    |
    */
    'storage' => 'file', // 'memory' veya 'file'

    /*
    |--------------------------------------------------------------------------
    | Log Dosya Yolu
    |--------------------------------------------------------------------------
    |
    | 'storage' değeri 'file' olduğunda kullanılacak log dosyası yolu.
    |
    */
    'log_path' => storage_path('logs/index-analyzer.log'),

    /*
    |--------------------------------------------------------------------------
    | Sorgu Optimizasyonu
    |--------------------------------------------------------------------------
    |
    | SQL sorgu kayıt ve analiz sürecini optimize etmek için ayarlar
    |
    */
    'query_optimization' => [
        'normalize_queries' => true, // Sorguları normalleştir (parametreleri kaldır)
        'skip_duplicates' => true,  // Aynı yapıda olan sorguları atla
        'max_query_length' => 500,  // Kaydedilecek maksimum sorgu uzunluğu
    ],

    /*
    |--------------------------------------------------------------------------
    | Yoksayılan Tablolar
    |--------------------------------------------------------------------------
    |
    | Bu tablolara ait sorgular kaydedilmez ve analiz edilmez.
    |
    */
    'ignored_tables' => [
        'migrations',
        'jobs',
        'failed_jobs',
        'password_resets',
        'sessions',
        'personal_access_tokens',
        'cache',
        'oauth_access_tokens',
        'oauth_auth_codes',
        'oauth_clients',
        'oauth_personal_access_clients',
        'oauth_refresh_tokens',
    ],

    /*
    |--------------------------------------------------------------------------
    | Öneriler için Ayarlar
    |--------------------------------------------------------------------------
    |
    | İndeks önerileri için kriterleri belirleyin.
    |
    */
    'suggestions' => [
        'min_query_time' => 0.5,  // Millisaniye cinsinden, bu değerden daha hızlı sorgular önerilmez
        'min_query_count' => 5,   // Bu sayıdan daha az tekrarlanan sorgular önerilmez
        'ignore_tables' => [],    // Bu tablolar için indeks önerileri oluşturulmaz
    ],
];
return [
    /*
    |--------------------------------------------------------------------------
    | Index Analyzer Aktifleştirme
    |--------------------------------------------------------------------------
    |
    | Bu değer, Index Analyzer'ın aktif olup olmadığını belirler.
    | Genellikle sadece development ortamında aktif edilmesi önerilir.
    |
    */
    'enabled' => env('INDEX_ANALYZER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | SQL Sorgu Kayıt Yöntemi
    |--------------------------------------------------------------------------
    |
    | Sorguların nasıl kaydedileceğini belirler.
    | Desteklenen değerler: 'database', 'file'
    |
    */
    'storage' => env('INDEX_ANALYZER_STORAGE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log Dosya Yolu
    |--------------------------------------------------------------------------
    |
    | 'file' kayıt yöntemi kullanıldığında, sorguların kaydedileceği dosya yolu.
    |
    */
    'log_path' => storage_path('logs/sql-queries.log'),

    /*
    |--------------------------------------------------------------------------
    | Tarayıcı Ayarları
    |--------------------------------------------------------------------------
    |
    | Tarayıcı simülasyonu için ayarlar.
    |
    */
    'browser' => [
        'max_depth' => 5,          // Maksimum tarama derinliği
        'timeout' => 30,            // Sayfa yüklemesi için zaman aşımı (saniye)
        'concurrent_requests' => 3, // Eş zamanlı istek sayısı
        'user_agent' => 'Laravel Index Analyzer Browser',
    ],

    /*
    |--------------------------------------------------------------------------
    | DebugBar Ayarları
    |--------------------------------------------------------------------------
    |
    | DebugBar için ayarlar.
    |
    */
    'debug_bar' => [
        'position' => 'bottom',     // 'bottom', 'top'
        'theme' => 'light',         // 'light', 'dark'
        'auto_show' => true,        // Otomatik göster
    ],

    /*
    |--------------------------------------------------------------------------
    | İndeks Önerileri
    |--------------------------------------------------------------------------
    |
    | İndeks önerileri için kullanılacak ayarlar.
    |
    */
    'suggestions' => [
        'min_query_time' => 50,     // Minimum sorgu süresi (ms)
        'min_query_count' => 3,     // Minimum sorgu sayısı
        'ignore_tables' => [        // Yoksayılacak tablolar
            'migrations',
            'jobs',
            'failed_jobs',
            'password_resets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rota Prefix'i
    |--------------------------------------------------------------------------
    |
    | Index Analyzer API rotaları için önek.
    |
    */
    'route_prefix' => 'index-analyzer',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Index Analyzer rotaları için uygulanacak middleware'ler.
    |
    */
    'middleware' => ['web'],
];
