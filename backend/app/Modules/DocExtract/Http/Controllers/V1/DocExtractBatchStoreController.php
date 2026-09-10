<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'template_id' => ['nullable', 'uuid'],
            'split_pages' => ['sometimes', 'boolean'],
            'records' => ['sometimes', 'nullable', 'string'],
            'file_page_counts' => ['sometimes', 'nullable', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();

        $templateId = isset($data['template_id']) && is_string($data['template_id']) && $data['template_id'] !== ''
            ? $data['template_id']
            : null;

        $splitPages = $request->boolean('split_pages');
        $records = $this->parseRecords(isset($data['records']) ? (string) $data['records'] : null);
        $filePageCounts = $this->parseFilePageCounts(
            isset($data['file_page_counts']) ? (string) $data['file_page_counts'] : null,
        );

        $batch = $batches->create(
            $templateId,
            $request->file('files', []),
            $actor,
            $splitPages,
            $records,
            $filePageCounts,
        );

        // Lightweight payload — full detail is loaded on the batch page (avoids OOM on large PDFs).
        return $this->ok($batches->asListRow($batch), 201);
    }

    /**
     * @return list<array{file_index?: mixed, pages?: mixed, label?: mixed}>|null
     */
    private function parseRecords(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'records must be valid JSON.');
        }

        if (! is_array($decoded)) {
            abort(422, 'records must be a JSON array.');
        }

        /** @var list<array{file_index?: mixed, pages?: mixed, label?: mixed}> $decoded */
        return array_values($decoded);
    }

    /**
     * @return array<int, int>
     */
    private function parseFilePageCounts(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'file_page_counts must be valid JSON.');
        }

        if (! is_array($decoded)) {
            abort(422, 'file_page_counts must be a JSON object.');
        }

        $counts = [];
        foreach ($decoded as $index => $count) {
            $fileIndex = (int) $index;
            $pageCount = (int) $count;
            if ($fileIndex < 0 || $pageCount < 1) {
                continue;
            }
            $counts[$fileIndex] = $pageCount;
        }

        return $counts;
    }
}
