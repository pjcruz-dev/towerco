<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Services\ProjectOneDashboardService;
use App\Modules\ProjectOne\Services\ProjectOneMapDataService;
use App\Modules\Rollout\Services\RolloutDashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectOneDashboardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProjectOneDashboardService $service,
        RolloutDashboardMetricsService $rolloutMetrics,
        ProjectOneMapDataService $mapData,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:view'), 403);

        $payload = $service->build();

        if ($request->user()?->can('project_one:rollout:view')) {
            $rollout = $rolloutMetrics->build($request->user());
            if ($rollout !== null) {
                $payload = array_merge($payload, $this->mergeRolloutMetrics($payload, $rollout));
            }

            if ($this->shouldIncludeMap($request)) {
                $payload['map_pins'] = $mapData->buildPins();
            }
        }

        return $this->ok($payload);
    }

    private function shouldIncludeMap(Request $request): bool
    {
        if ($request->boolean('with_map')) {
            return true;
        }

        $include = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));

        return in_array('map', $include, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rollout
     * @return array<string, mixed>
     */
    private function mergeRolloutMetrics(array $payload, array $rollout): array
    {
        $payload['rollouts'] = $rollout;

        $payload['kpis'][] = [
            'key' => 'active_rollouts',
            'label' => 'Active rollouts',
            'value' => (string) $rollout['active_rollouts'],
            'change' => 'Open rollout programs',
            'tone' => 'success',
        ];
        $payload['kpis'][] = [
            'key' => 'rollout_day_one',
            'label' => 'Awaiting Day-1',
            'value' => (string) $rollout['awaiting_day_one'],
            'change' => 'TSSR / DOA / site license',
            'tone' => $rollout['awaiting_day_one'] > 0 ? 'warning' : 'neutral',
        ];
        $payload['kpis'][] = [
            'key' => 'rollout_sla_risk',
            'label' => 'Rollout SLA risk',
            'value' => (string) $rollout['sla_at_risk'],
            'change' => '≤10 working days to RFI',
            'tone' => $rollout['sla_at_risk'] > 0 ? 'danger' : 'success',
        ];
        $payload['kpis'][] = [
            'key' => 'rollout_pending_gates',
            'label' => 'Pending gates',
            'value' => (string) $rollout['pending_gates'],
            'change' => 'Timeline gates awaiting decision',
            'tone' => $rollout['pending_gates'] > 0 ? 'warning' : 'neutral',
        ];
        $payload['kpis'][] = [
            'key' => 'gate_approvals_awaiting_me',
            'label' => 'Awaiting my approval',
            'value' => (string) ($rollout['gate_approvals_awaiting_me'] ?? 0),
            'change' => 'Formal gate approval inbox',
            'tone' => ($rollout['gate_approvals_awaiting_me'] ?? 0) > 0 ? 'danger' : 'success',
        ];
        $payload['kpis'][] = [
            'key' => 'rollout_open_saq',
            'label' => 'Open SAQ programs',
            'value' => (string) $rollout['open_saq_programs'],
            'change' => 'Fewer than 3 candidates',
            'tone' => $rollout['open_saq_programs'] > 0 ? 'warning' : 'success',
        ];

        array_unshift($payload['actions'], [
            'id' => 'ac-rollouts',
            'label' => 'Active rollouts',
            'count' => (int) $rollout['active_rollouts'],
            'href' => '/project-one/rollouts',
            'priority' => $rollout['sla_at_risk'] > 0 ? 'high' : 'normal',
        ]);

        if (($rollout['gate_approvals_awaiting_me'] ?? 0) > 0) {
            array_unshift($payload['actions'], [
                'id' => 'ac-gate-approvals',
                'label' => 'Gate approvals awaiting you',
                'count' => (int) $rollout['gate_approvals_awaiting_me'],
                'href' => '/project-one/gate-approvals?awaiting_me=1',
                'priority' => 'high',
            ]);
        }

        if ($rollout['awaiting_day_one'] > 0) {
            $payload['actions'][] = [
                'id' => 'ac-rollout-day-one',
                'label' => 'Rollouts awaiting Day-1',
                'count' => (int) $rollout['awaiting_day_one'],
                'href' => '/project-one/rollouts',
                'priority' => 'high',
            ];
        }

        return $payload;
    }
}
