<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IndexAnalyzer Aktif/Pasif Durumu
    |--------------------------------------------------------------------------
    |
    | Bu paketi etkinleştirmek veya devre dışı bırakmak için bu ayarı kullanın.
    | Genellikle sadece development ortamında aktif edilmesi önerilir.
    |
    */
    'enabled' => env('INDEX_ANALYZER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Depolama Ayarları
    |--------------------------------------------------------------------------
    |
    | Sorgu verilerinin nerede saklanacağı.
    | Desteklenen değerler: 'memory', 'file'
    |
    */
    'storage' => env('INDEX_ANALYZER_STORAGE', 'file'),

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
    | Maksimum Log Dosyası Boyutu
    |--------------------------------------------------------------------------
    |
    | Log dosyası bu boyuta ulaştığında otomatik olarak yeni dosya oluşturulur.
    | Boyut byte cinsinden belirtilir (10MB = 10 * 1024 * 1024)
    |
    */
    'max_log_size' => 10 * 1024 * 1024, // 10MB

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
        'min_query_time' => 1000,  // Millisaniye cinsinden, bu değerden daha hızlı sorgular önerilmez
        'min_query_count' => 1,   // Bu sayıdan daha az tekrarlanan sorgular önerilmez
        'ignore_tables' => [],    // Bu tablolar için indeks önerileri oluşturulmaz
    ],
];
