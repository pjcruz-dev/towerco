<?php

declare(strict_types=1);

return [
    'service_url' => rtrim((string) env('DOC_EXTRACT_URL', 'http://doc-extract:8081'), '/'),
    'timeout_seconds' => (int) env('DOC_EXTRACT_TIMEOUT', 120),
    'retention_days' => (int) env('DOC_EXTRACT_RETENTION_DAYS', 7),
    'max_files_per_batch' => (int) env('DOC_EXTRACT_MAX_FILES_PER_BATCH', 25),
    'max_file_size_kb' => (int) env('DOC_EXTRACT_MAX_FILE_SIZE_KB', 20480),
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
    ],
];
