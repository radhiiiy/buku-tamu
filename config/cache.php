<?php

return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'database' => ['driver' => 'database', 'connection' => env('DB_CACHE_CONNECTION'), 'table' => 'cache', 'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'), 'lock_table' => 'cache_locks'],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
    ],
    'prefix' => env('CACHE_PREFIX', 'trimas-cache-'),
];
