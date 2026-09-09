<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Models\DocExtractTemplate;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractFieldMapper;
use App\Modules\DocExtract\Services\DocExtractFileStorageService;
use App\Modules\DocExtract\Services\DocExtractScanClient;
use App\Modules\DocExtract\Support\DocExtractDocumentStatus;
use Illuminate\Support\Facades\Log;

final class ProcessDocExtractDocumentJob extends AbstractQueuedJob
{
    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $documentId,
    ) {
        parent::__construct();
        // Use default queue so the EC2 systemd worker (queue:work redis) picks jobs up
        // without requiring a separate toweros-integrations supervisor.
        $this->onQueue(config('toweros.queues.default'));
    }

    public function handle(
        DocExtractScanClient $scanClient,
        DocExtractFileStorageService $storage,
        DocExtractFieldMapper $mapper,
        DocExtractBatchService $batches,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($scanClient, $storage, $mapper, $batches): void {
            $document = DocExtractDocument::query()->find($this->documentId);
            if ($document === null) {
                return;
            }
            if ($document->stored_path === null || $document->purged_at !== null) {
                $document->status = DocExtractDocumentStatus::FAILED;
                $document->error_message = 'Document file is missing.';
                $document->save();
                $this->refreshBatch($batches, (string) $document->batch_id);

                return;
            }

            $document->status = DocExtractDocumentStatus::SCANNING;
            $document->error_message = null;
            $document->save();

            try {
                $bytes = $storage->readBytes((string) $document->stored_path);
                $template = $document->template_id
                    ? DocExtractTemplate::query()->find($document->template_id)
                    : null;
                $fields = $template !== null && is_array($template->fields) ? $template->fields : [];

                $result = $scanClient->scan(
                    (string) $document->original_filename,
                    (string) ($document->mime_type ?? 'application/pdf'),
                    $bytes,
                    $fields,
                );

                $pageTexts = [];
                foreach ($result['pages'] as $page) {
                    $pageTexts[] = trim($page['text']);
                }
                $extractedText = trim(implode("\n\n", array_filter($pageTexts, static fn (string $t): bool => $t !== '')));

                $mapped = $result['field_values'] ?? [];
                if ((! is_array($mapped) || $mapped === []) && $fields !== []) {
                    $mapped = $mapper->map($fields, $extractedText);
                }

                $discoveredFields = is_array($result['discovered_fields'] ?? null)
                    ? $result['discovered_fields']
                    : [];

                $document->scan_engine = $result['engine'];
                $document->extracted_text = $extractedText !== '' ? $extractedText : null;
                $document->scan_meta = [
                    'warnings' => $result['warnings'],
                    'page_count' => count($result['pages']),
                    'mapper' => (string) ($result['mode'] ?? 'python'),
                    'mode' => (string) ($result['mode'] ?? ($fields !== [] ? 'template' : 'auto')),
                    'discovered_fields' => $discoveredFields,
                ];
                $document->field_values = is_array($mapped) ? $mapped : [];
                $document->status = DocExtractDocumentStatus::READY;
                $document->error_message = null;
                $document->save();
            } catch (\Throwable $exception) {
                Log::warning('DocExtract scan failed', [
                    'document_id' => $this->documentId,
                    'message' => $exception->getMessage(),
                ]);
                $document->status = DocExtractDocumentStatus::FAILED;
                $document->error_message = mb_substr($exception->getMessage(), 0, 1000);
                $document->save();
            }

            $this->refreshBatch($batches, (string) $document->batch_id);
        });
    }

    private function refreshBatch(DocExtractBatchService $batches, string $batchId): void
    {
        $batch = DocExtractBatch::query()->find($batchId);
        if ($batch !== null) {
            $batches->refreshBatchCounters($batch);
        }
    }
}
