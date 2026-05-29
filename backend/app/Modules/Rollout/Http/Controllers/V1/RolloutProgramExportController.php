<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutProgramExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RolloutProgramExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, RolloutProgramExportService $export): StreamedResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $filters = $this->validatedRolloutExportFilters($request);
        $filters = array_merge($filters, [
            'search' => $query['search'],
        ]);

        $filename = 'rollouts-full-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($export, $filters): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $export->headers($filters));

            foreach ($export->rows($filters) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{status?: string, mno?: string, project_type?: string, region?: string, sort?: string, sla_at_risk?: bool}
     */
    private function validatedRolloutExportFilters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:32'],
            'mno' => ['sometimes', 'string', 'max:32'],
            'project_type' => ['sometimes', 'string', 'max:32'],
            'region' => ['sometimes', 'string', 'max:64'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'sla_at_risk' => ['sometimes', 'boolean'],
        ]);

        return array_filter([
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'mno' => isset($validated['mno']) ? (string) $validated['mno'] : null,
            'project_type' => isset($validated['project_type']) ? (string) $validated['project_type'] : null,
            'region' => isset($validated['region']) ? (string) $validated['region'] : null,
            'sort' => isset($validated['sort']) ? (string) $validated['sort'] : null,
            'sla_at_risk' => isset($validated['sla_at_risk']) ? (bool) $validated['sla_at_risk'] : null,
        ], static fn (mixed $value) => $value !== null && $value !== '');
    }
}
