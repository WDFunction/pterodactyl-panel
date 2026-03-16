<?php

return [
    'enabled' => env('FILE_TRANSFER_S3_ENABLED', false),
    'icon_url' => env('FILE_TRANSFER_S3_ICON_URL', ''),
    'public_base_url' => env('FILE_TRANSFER_S3_PUBLIC_URL'),
    'presigned_url_lifespan' => env('FILE_TRANSFER_URL_LIFESPAN', 60),
    'prefix' => 'file-transfers',
    'disk' => [
        'driver' => 's3',
        'region' => env('FILE_TRANSFER_S3_REGION', 'oss-cn-hangzhou'),
        'key' => env('FILE_TRANSFER_S3_KEY'),
        'secret' => env('FILE_TRANSFER_S3_SECRET'),
        'bucket' => env('FILE_TRANSFER_S3_BUCKET'),
        'endpoint' => env('FILE_TRANSFER_S3_ENDPOINT'),
        'use_path_style_endpoint' => env('FILE_TRANSFER_S3_PATH_STYLE', false),
    ],
];
