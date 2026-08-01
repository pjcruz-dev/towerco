<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalReportStoreController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalReportService $reports): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $data = $this->validatedPayload($request);
        $report = $reports->create($request->user(), $data);

        return $this->ok($reports->present($report), 201);
    }

    /**
     * @return array{
     *     name: string,
     *     description?: string|null,
     *     filters?: array<string, mixed>|null,
     *     columns?: list<string>|null,
     *     layout?: string,
     *     format?: string,
     *     grid_field_id?: string|null,
     *     schedule?: array<string, mixed>|null
     * }
     */
    private function validatedPayload(Request $request): array
    {
        /** @var array{
         *     name: string,
         *     description?: string|null,
         *     filters?: array<string, mixed>|null,
         *     columns?: list<string>|null,
         *     layout?: string,
         *     format?: string,
         *     grid_field_id?: string|null,
         *     schedule?: array<string, mixed>|null
         * } $data
         */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
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

        return $data;
    }
}
