<?php

use Illuminate\Support\Str;

return [
    'default' => 'array',

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => Str::slug('laravel', '_') . '_cache_',
];