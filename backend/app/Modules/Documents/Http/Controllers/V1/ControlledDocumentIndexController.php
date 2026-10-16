<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ControlledDocumentIndexController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentRegistryService $registry, ControlledDocumentAccessService $access): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete', 'incoming'])],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'review' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'due_30', 'due_90', 'none'])],
            'file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
            'effective_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'effective_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'published_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'published_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:published_from'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $result = $registry->paginate(
            actor: $user,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 25),
            search: $data['search'] ?? null,
            department: $data['department'] ?? null,
            status: $data['status'] ?? null,
            documentType: $data['document_type'] ?? null,
            sort: $data['sort'] ?? null,
            filters: [
                'review' => $data['review'] ?? null,
                'file' => $data['file'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'published_from' => $data['published_from'] ?? null,
                'published_to' => $data['published_to'] ?? null,
            ],
        );

        $incoming = is_array($result['incoming_by_code'] ?? null) ? $result['incoming_by_code'] : [];
        $documents = $result['documents']->through(function (ControlledDocument $doc) use ($incoming): array {
            $open = $incoming[(string) $doc->document_code] ?? null;

            return [
                'id' => (string) $doc->id,
                'document_code' => $doc->document_code,
                'e_approval_form_id' => $doc->e_approval_form_id,
                'title' => $doc->title,
                'document_type' => $doc->document_type,
                'department' => $doc->department,
                'current_revision' => (int) $doc->current_revision,
                'status' => $doc->status,
                'effective_date' => $doc->effective_date?->toDateString(),
                'next_review_date' => $doc->next_review_date?->toDateString(),
                'published_at' => $doc->published_at?->toIso8601String(),
                'created_by_name' => $doc->createdBy?->name,
                'has_file' => (bool) $doc->getAttribute('has_file'),
                'incoming_revision' => is_array($open) ? $open['revision_number'] : null,
                'incoming_submission_id' => is_array($open) ? $open['submission_id'] : null,
                'incoming_document_no' => is_array($open) ? $open['document_no'] : null,
            ];
        });

        return $this->ok([
            'kpis' => $result['kpis'],
            'facets' => $result['facets'],
            'documents' => $documents,
        ]);
    }
}
