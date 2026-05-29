<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use Illuminate\Support\Facades\Schema;

final class RolloutGateApprovalExportService
{
    /**
     * @return list<array<int, string|null>>
     */
    public function rows(?string $status = null): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_gate_approval_requests')) {
            return [];
        }

        $query = RolloutGateApprovalRequest::query()
            ->with(['rolloutProgram', 'timelinePhase', 'requestedBy'])
            ->orderByDesc('submitted_at');

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        $header = [
            'request_id',
            'rollout_ref',
            'phase_key',
            'phase_label',
            'gate_label',
            'status',
            'current_step',
            'current_approver_role',
            'approval_chain',
            'requested_by',
            'submitted_at',
            'completed_at',
            'request_notes',
            'rejection_notes',
            'step_log_json',
        ];

        $rows = [$header];

        foreach ($query->get() as $request) {
            $rows[] = [
                $request->id,
                $request->rolloutProgram?->rollout_ref,
                $request->phase_key,
                $request->timelinePhase?->label,
                $request->gate_label,
                $request->status,
                (string) ($request->current_step + 1),
                $request->currentApproverRole(),
                implode(' > ', $request->approval_chain ?? []),
                $request->requestedBy?->name,
                $request->submitted_at?->toIso8601String(),
                $request->completed_at?->toIso8601String(),
                $request->request_notes,
                $request->rejection_notes,
                json_encode($request->step_log ?? [], JSON_THROW_ON_ERROR),
            ];
        }

        return $rows;
    }
}
