<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [


        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'aktienki'),
            'username' => env('DB_USERNAME', 'aktienki_app'),
            'password' => env('DB_PASSWORD', 'stockpredictor'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        // Compact, read-optimized production output of pipeline-next. This is
        // intentionally separate from the Laravel application database: no
        // training rows, price history, features or backtest trades belong
        // here. Keep the default connection on `pgsql` until the serving
        // compatibility checks and the canary cutover have passed.
        'serving' => [
            'driver' => 'pgsql',
            'url' => env('SERVING_DB_URL'),
            'host' => env('SERVING_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('SERVING_DB_PORT', env('DB_PORT', '5432')),
            'database' => env('SERVING_DB_DATABASE', 'aktienki_serving_next'),
            'username' => env('SERVING_DB_USERNAME', env('DB_USERNAME', 'aktienki_app')),
            'password' => env('SERVING_DB_PASSWORD', env('DB_PASSWORD')),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('SERVING_DB_SSLMODE', env('DB_SSLMODE', 'prefer')),
        ],

    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
