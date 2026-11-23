<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionPrintService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalRoleFieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionPrintDataController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionPrintService $print,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $model = $print->buildPrintModel($submission);
        unset($model['submission']);
        $roleAccess = app(EApprovalRoleFieldAccess::class);
        abort_unless($roleAccess->allows($request->user(), 'view'), 403);
        $model = $roleAccess->redactListRow($request->user(), $model, 'record');
        if (isset($model['fields']) && is_array($model['fields'])) {
            $model['fields'] = array_values(array_filter(
                $model['fields'],
                fn (mixed $field): bool => is_array($field) && $roleAccess->visible($request->user(), (string) ($field['key'] ?? ''), 'record'),
            ));
        }
        if (isset($model['grids']) && is_array($model['grids'])) {
            $model['grids'] = array_values(array_filter(
                $model['grids'],
                fn (mixed $grid): bool => is_array($grid) && $roleAccess->visible($request->user(), (string) ($grid['key'] ?? ''), 'record'),
            ));
        }

        return $this->ok($model);
    }
}
