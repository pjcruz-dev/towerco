<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentEApprovalValuesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ControlledDocumentLookupController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentEApprovalValuesService $lookup): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->can('e_approval:submissions:create') || $user?->can('documents:controlled:view'),
            403,
        );

        $documentCode = trim((string) $request->query('document_code', ''));
        if ($documentCode === '') {
            return $this->ok([
                'exists' => false,
                'document_code' => null,
                'next_revision' => 0,
            ]);
        }

        $found = $lookup->lookupByDocumentCode($documentCode);
        if ($found === null) {
            return $this->ok([
                'exists' => false,
                'document_code' => $documentCode,
                'next_revision' => 0,
            ]);
        }

        return $this->ok($found);
    }
}
