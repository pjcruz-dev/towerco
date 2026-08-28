<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionExportColumnsController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalSubmissionExportService $export): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $validated = $request->validate([
            'form_id' => ['sometimes', 'uuid'],
        ]);

        $form = isset($validated['form_id'])
            ? EApprovalForm::query()->find($validated['form_id'])
            : null;

        $grids = $form !== null
            ? $export->gridFields($form)->map(static fn ($field): array => [
                'key' => (string) $field->id,
                'label' => trim((string) ($field->label ?? '')) !== ''
                    ? (string) $field->label
                    : (string) $field->name,
            ])->values()->all()
            : [];

        // Export picker needs every form (published or not) under export permission —
        // not the submissions form-index endpoint (different RBAC + per_page caps).
        $forms = EApprovalForm::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(static fn (EApprovalForm $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'status' => (string) ($row->status ?? 'draft'),
            ])
            ->values()
            ->all();

        $canViewAll = $user->can('e_approval:audit:view') || $user->can('e_approval:forms:manage');

        return response()->json([
            'data' => $export->columns($form, $form !== null),
            'grids' => $grids,
            'forms' => $forms,
            'can_view_all' => $canViewAll,
        ]);
    }
}
