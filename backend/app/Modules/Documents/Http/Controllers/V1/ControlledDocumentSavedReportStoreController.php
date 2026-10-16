<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentSavedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ControlledDocumentSavedReportStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentSavedReportService $reports,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        $data = $request->validate($this->rules(requiredFilters: true));

        return $this->created($reports->present($reports->create($user, $data)));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $requiredFilters): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'filters' => [$requiredFilters ? 'required' : 'sometimes', 'array'],
            'filters.period' => ['sometimes', 'integer', Rule::in([30, 90, 365])],
            'filters.department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filters.document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filters.status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete'])],
            'filters.review' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'due_30', 'due_90', 'none'])],
            'filters.file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
            'schedule' => ['sometimes', 'nullable', 'array'],
            'schedule.enabled' => ['sometimes', 'boolean'],
            'schedule.frequency' => ['sometimes', 'string', Rule::in(['daily', 'weekly'])],
            'schedule.hour' => ['sometimes', 'integer', 'min:0', 'max:23'],
            'schedule.day_of_week' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'schedule.recipients' => ['sometimes', 'array', 'max:10'],
            'schedule.recipients.*' => ['email', 'max:255'],
        ];
    }
}
