<?php

declare(strict_types=1);

return [
    'service_url' => rtrim((string) env('DOC_EXTRACT_URL', 'http://doc-extract:8081'), '/'),
    // A 35-page scanned bill is one OCR request. 120s returns before the sidecar writes a byte.
    'timeout_seconds' => (int) env('DOC_EXTRACT_TIMEOUT', 300),
    'retention_days' => (int) env('DOC_EXTRACT_RETENTION_DAYS', 7),
    'max_files_per_batch' => (int) env('DOC_EXTRACT_MAX_FILES_PER_BATCH', 500),
    /** Max pages expanded when split_pages is enabled for a single PDF. */
    'max_pages_per_file' => (int) env('DOC_EXTRACT_MAX_PAGES_PER_FILE', 50),
    /**
     * Always prefer a real queue for OCR. Sync runs scans inside the HTTP process and
     * OOMs / stalls large consolidated batches on PHP's built-in server.
     */
    'queue_connection' => env('DOC_EXTRACT_QUEUE_CONNECTION', 'redis'),
    'max_file_size_kb' => (int) env('DOC_EXTRACT_MAX_FILE_SIZE_KB', 51200),
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
    ],
];
