<?php

return [
    'enabled' => true,

    // Sorguların taranacağı dizin
    'scan_path' => 'base_path',

    // Hariç tutulacak dizinler
    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
        'tests',
    ],

    // Model dizinleri
    'model_paths' => [
        'app/Models',
    ],
];
