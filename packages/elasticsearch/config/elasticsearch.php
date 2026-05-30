<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Hosts
    |--------------------------------------------------------------------------
    | A list of node hosts for self-managed deployments. Ignored when
    | cloud_id is set.
    */
    'hosts' => [
        env('ELASTICSEARCH_HOST', 'https://localhost:9200'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Elastic Cloud
    |--------------------------------------------------------------------------
    | Set your Cloud ID from the "My deployment" dashboard. When present,
    | this takes precedence over the hosts array.
    */
    'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | method: "api_key" (recommended for cloud) or "basic" (self-managed).
    */
    'auth' => [
        'method'   => env('ELASTICSEARCH_AUTH_METHOD', 'api_key'),
        'api_key'  => env('ELASTICSEARCH_API_KEY'),
        'username' => env('ELASTICSEARCH_USERNAME', 'elastic'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL / TLS
    |--------------------------------------------------------------------------
    | Set verify to false only for local dev. For self-managed clusters,
    | point ca_bundle to the PEM exported from your Elasticsearch node.
    */
    'ssl' => [
        'verify'    => env('ELASTICSEARCH_SSL_VERIFY', true),
        'ca_bundle' => env('ELASTICSEARCH_CA_BUNDLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    | Prepended to every index name, useful for environment separation.
    | e.g. prefix "staging" → index "staging_products".
    */
    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Index Definitions
    |--------------------------------------------------------------------------
    | Keys match the logical index name used in toSearchableArray() and
    | SearchQueryDTO. Settings and mappings are applied when you run:
    |   php artisan elasticsearch:index create <name>
    */
    'indices' => [

        'products' => [
            'settings' => [
                'number_of_shards'   => 1,
                'number_of_replicas' => 1,
            ],
            'mappings' => [
                'properties' => [
                    'id'          => ['type' => 'keyword'],
                    'name'        => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'price'       => ['type' => 'double'],
                    'category'    => ['type' => 'keyword'],
                    'status'      => ['type' => 'keyword'],
                    'created_at'  => ['type' => 'date'],
                    'updated_at'  => ['type' => 'date'],
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Connection and queue name used by the background sync jobs
    | (IndexDocumentJob, UpdateDocumentJob, DeleteDocumentJob).
    */
    'queue' => [
        'connection' => env('ELASTICSEARCH_QUEUE_CONNECTION', 'default'),
        'name'       => env('ELASTICSEARCH_QUEUE', 'elasticsearch'),
    ],

];
