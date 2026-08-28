<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalReportUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $report,
        EApprovalReportService $reports,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $model = EApprovalReportDefinition::query()->findOrFail($report);
        abort_unless((string) $model->user_id === (string) $user->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'filters' => ['sometimes', 'array'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:120'],
            'layout' => ['sometimes', 'string', 'in:submissions,line_items'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx'],
            'grid_field_id' => ['nullable', 'uuid'],
            'schedule' => ['sometimes', 'nullable', 'array'],
            'schedule.enabled' => ['sometimes', 'boolean'],
            'schedule.frequency' => ['sometimes', 'string', 'in:daily,weekly'],
            'schedule.hour' => ['sometimes', 'integer', 'min:0', 'max:23'],
            'schedule.day_of_week' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'schedule.recipients' => ['sometimes', 'array'],
            'schedule.recipients.*' => ['email', 'max:255'],
        ]);

        $updated = $reports->update($model, $data);

        return $this->ok($reports->present($updated));
    }
}
