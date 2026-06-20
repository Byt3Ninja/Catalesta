<?php

declare(strict_types=1);

return [
    // Disk used for content-addressed blob storage. Defaults to the MinIO-backed
    // 's3' Flysystem disk (docker-compose: AWS_ENDPOINT=http://minio:9000).
    'disk' => env('BLOB_DISK', 's3'),

    // Fail-closed ceiling on a single blob's size (bytes). Oversize content is
    // rejected before any write — never stored partially. Default 25 MiB.
    'max_bytes' => (int) env('BLOB_MAX_BYTES', 25 * 1024 * 1024),

    // Path prefix for stored objects; keys fan out as <prefix>/ab/cd/<sha256>.
    'path_prefix' => env('BLOB_PATH_PREFIX', 'blobs'),
];
