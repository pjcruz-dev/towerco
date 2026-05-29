<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutPlaybookConfigureController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutProgramService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $data = $request->validate([
            'day_overrides' => ['sometimes', 'array'],
            'gate_approval_policies' => ['sometimes', 'array'],
            'email_notification_policies' => ['sometimes', 'array'],
            'gate_approval_escalation_working_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        if (array_key_exists('day_overrides', $data)) {
            $service->applyDayOverrides($data['day_overrides']);
        }

        if (array_key_exists('gate_approval_policies', $data)) {
            $service->applyGateApprovalPolicies($data['gate_approval_policies']);
        }

        if (array_key_exists('email_notification_policies', $data)) {
            $service->applyEmailNotificationPolicies($data['email_notification_policies']);
        }

        if (array_key_exists('gate_approval_escalation_working_days', $data)) {
            $service->applyGateApprovalEscalationWorkingDays((int) $data['gate_approval_escalation_working_days']);
        }

        return $this->ok($service->playbookStatus());
    }
}
