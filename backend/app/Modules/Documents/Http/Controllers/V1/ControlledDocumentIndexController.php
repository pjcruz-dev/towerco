<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $result = $registry->paginate(
            actor: $user,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 25),
            search: $data['search'] ?? null,
            department: $data['department'] ?? null,
            status: $data['status'] ?? null,
            documentType: $data['document_type'] ?? null,
        );

        $documents = $result['documents']->through(static fn (ControlledDocument $doc): array => [
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
        ]);

        return $this->ok([
            'kpis' => $result['kpis'],
            'documents' => $documents,
        ]);
    }
}
