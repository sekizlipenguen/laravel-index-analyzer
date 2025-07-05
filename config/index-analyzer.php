<?php

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
