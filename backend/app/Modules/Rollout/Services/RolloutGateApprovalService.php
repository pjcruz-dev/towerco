<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class RolloutGateApprovalService
{
    public function __construct(
        private readonly RolloutGateApprovalPolicyService $policies,
        private readonly RolloutGateApproverResolver $approvers,
        private readonly RolloutProgramService $programService,
        private readonly RolloutAuditLogger $audit,
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly RolloutGateApprovalEscalationService $escalation,
        private readonly RolloutGateApprovalNotificationDispatcher $notificationDispatcher,
    ) {}

    public function submit(
        RolloutTimelinePhase $phase,
        ?string $requestNotes = null,
        ?Authenticatable $actor = null,
    ): RolloutGateApprovalRequest {
        $phase->load('rolloutProgram');
        $program = $phase->rolloutProgram;

        if ($program === null) {
            throw ValidationException::withMessages([
                'phase' => [__('Rollout not found for this phase.')],
            ]);
        }

        $this->assertProgramEditable($program);

        $policy = $this->policies->policyForPhase(
            (string) $program->project_type,
            (string) $phase->phase_key,
            TenantRolloutPlaybookConfig::query()->first(),
        );

        if ($policy === null) {
            throw ValidationException::withMessages([
                'gate' => [__('Formal approval is not required for this gate.')],
            ]);
        }

        $open = RolloutGateApprovalRequest::query()
            ->where('rollout_timeline_phase_id', $phase->id)
            ->where('status', RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'gate' => [__('An approval request is already in review for this gate.')],
            ]);
        }

        /** @var TenantUser|null $actorUser */
        $actorUser = $actor instanceof TenantUser ? $actor : null;

        /** @var RolloutGateApprovalRequest $request */
        $request = RolloutGateApprovalRequest::query()->create([
            'rollout_program_id' => $program->id,
            'rollout_timeline_phase_id' => $phase->id,
            'phase_key' => $phase->phase_key,
            'gate_label' => $phase->gate_label,
            'status' => RolloutGateApprovalRequest::STATUS_IN_REVIEW,
            'current_step' => 0,
            'approval_chain' => $policy['chain'],
            'step_log' => [],
            'request_notes' => $requestNotes !== null && trim($requestNotes) !== '' ? trim($requestNotes) : null,
            'requested_by_id' => $actorUser?->id,
            'submitted_at' => now(),
            'current_step_started_at' => now(),
        ]);

        $request->load(['rolloutProgram', 'timelinePhase', 'requestedBy']);

        $this->notificationDispatcher->dispatch($request, 'submitted', $actorUser?->name, $actorUser);

        $this->audit->log('rollout.gate_approval_submitted', $program, [
            'phase_key' => $phase->phase_key,
            'gate_label' => $phase->gate_label,
            'request_id' => $request->id,
            'chain' => $policy['chain'],
        ], $actor);

        return $request->fresh(['rolloutProgram', 'timelinePhase', 'requestedBy']);
    }

    public function approveStep(
        RolloutGateApprovalRequest $request,
        ?string $notes = null,
        ?Authenticatable $actor = null,
    ): RolloutGateApprovalRequest {
        $request->load(['rolloutProgram', 'timelinePhase', 'requestedBy']);
        $program = $request->rolloutProgram;
        $phase = $request->timelinePhase;

        if ($program === null || $phase === null) {
            throw ValidationException::withMessages([
                'request' => [__('Approval request is incomplete.')],
            ]);
        }

        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'request' => [__('This approval request is no longer open.')],
            ]);
        }

        /** @var TenantUser $actorUser */
        $actorUser = $actor instanceof TenantUser ? $actor : Auth::user();
        $role = $request->currentApproverRole();

        if ($role === null || ! $this->approvers->canActOnStep($actorUser, $program, $role)) {
            throw ValidationException::withMessages([
                'request' => [__('You are not authorized to approve this step.')],
            ]);
        }

        $stepLog = $request->step_log ?? [];
        $stepLog[] = [
            'step' => $request->current_step,
            'role' => $role,
            'status' => 'approved',
            'user_id' => $actorUser->id,
            'user_name' => $actorUser->name,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'at' => now()->toIso8601String(),
        ];

        $chain = $request->approval_chain ?? [];
        $nextStep = $request->current_step + 1;

        if ($nextStep >= count($chain)) {
            $request->status = RolloutGateApprovalRequest::STATUS_APPROVED;
            $request->current_step = $nextStep;
            $request->step_log = $stepLog;
            $request->completed_at = now();
            $request->save();

            $this->programService->updatePhaseGateStatus($phase, 'passed');

            $this->notificationDispatcher->dispatch($request, 'approved', $actorUser->name, $actorUser);
            $this->audit->log('rollout.gate_approval_completed', $program, [
                'phase_key' => $phase->phase_key,
                'request_id' => $request->id,
            ], $actorUser);

            return $request->fresh(['rolloutProgram', 'timelinePhase', 'requestedBy']);
        }

        $request->current_step = $nextStep;
        $request->step_log = $stepLog;
        $request->current_step_started_at = now();
        $request->last_escalated_at = null;
        $request->save();

        $this->notificationDispatcher->dispatch($request, 'step_approved', $actorUser->name, $actorUser);

        $this->audit->log('rollout.gate_approval_step_approved', $program, [
            'phase_key' => $phase->phase_key,
            'request_id' => $request->id,
            'step' => $nextStep - 1,
            'next_role' => $chain[$nextStep] ?? null,
        ], $actorUser);

        return $request->fresh(['rolloutProgram', 'timelinePhase', 'requestedBy']);
    }

    public function reject(
        RolloutGateApprovalRequest $request,
        ?string $rejectionNotes = null,
        ?Authenticatable $actor = null,
    ): RolloutGateApprovalRequest {
        $request->load(['rolloutProgram', 'timelinePhase', 'requestedBy']);
        $program = $request->rolloutProgram;
        $phase = $request->timelinePhase;

        if ($program === null || $phase === null) {
            throw ValidationException::withMessages([
                'request' => [__('Approval request is incomplete.')],
            ]);
        }

        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'request' => [__('This approval request is no longer open.')],
            ]);
        }

        /** @var TenantUser $actorUser */
        $actorUser = $actor instanceof TenantUser ? $actor : Auth::user();
        $role = $request->currentApproverRole();

        if ($role === null || ! $this->approvers->canActOnStep($actorUser, $program, $role)) {
            throw ValidationException::withMessages([
                'request' => [__('You are not authorized to reject this step.')],
            ]);
        }

        $notes = $rejectionNotes !== null && trim($rejectionNotes) !== '' ? trim($rejectionNotes) : null;

        $stepLog = $request->step_log ?? [];
        $stepLog[] = [
            'step' => $request->current_step,
            'role' => $role,
            'status' => 'rejected',
            'user_id' => $actorUser->id,
            'user_name' => $actorUser->name,
            'notes' => $notes,
            'at' => now()->toIso8601String(),
        ];

        $request->status = RolloutGateApprovalRequest::STATUS_REJECTED;
        $request->rejection_notes = $notes;
        $request->step_log = $stepLog;
        $request->completed_at = now();
        $request->save();

        // Gate stays pending for resubmit (do not set failed).
        if ($phase->gate_status === 'passed') {
            $phase->gate_status = 'pending';
            $phase->actual_end_date = null;
            $phase->save();
        }

        $this->notificationDispatcher->dispatch($request, 'rejected', $actorUser->name, $actorUser);
        $this->audit->log('rollout.gate_approval_rejected', $program, [
            'phase_key' => $phase->phase_key,
            'request_id' => $request->id,
            'notes' => $notes,
        ], $actorUser);

        return $request->fresh(['rolloutProgram', 'timelinePhase', 'requestedBy']);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function index(
        ?TenantUser $viewer,
        ?string $status = null,
        bool $mineOnly = false,
        bool $awaitingMeOnly = false,
        int $page = 1,
        int $perPage = 25,
    ): array {
        if ($awaitingMeOnly) {
            $status = RolloutGateApprovalRequest::STATUS_IN_REVIEW;
        }

        $query = RolloutGateApprovalRequest::query()
            ->with(['rolloutProgram', 'timelinePhase', 'requestedBy'])
            ->orderByDesc('submitted_at');

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($mineOnly && $viewer !== null) {
            $query->where('requested_by_id', $viewer->id);
        }

        if ($awaitingMeOnly && $viewer !== null) {
            $filtered = $query->get()
                ->filter(fn (RolloutGateApprovalRequest $row) => $this->viewerCanAct($row, $viewer))
                ->values();
            $total = $filtered->count();
            $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

            return [
                'data' => $items->map(fn (RolloutGateApprovalRequest $row) => $this->presentRequest($row, $viewer))->values()->all(),
                'meta' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ];
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (RolloutGateApprovalRequest $row) => $this->presentRequest($row, $viewer))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function countAwaitingUser(?TenantUser $viewer): int
    {
        if ($viewer === null) {
            return 0;
        }

        return RolloutGateApprovalRequest::query()
            ->with(['rolloutProgram'])
            ->where('status', RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->get()
            ->filter(fn (RolloutGateApprovalRequest $row) => $this->viewerCanAct($row, $viewer))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewAwaitingUser(?TenantUser $viewer, int $limit = 5): array
    {
        if ($viewer === null) {
            return [];
        }

        return RolloutGateApprovalRequest::query()
            ->with(['rolloutProgram', 'timelinePhase', 'requestedBy'])
            ->where('status', RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn (RolloutGateApprovalRequest $row) => $this->viewerCanAct($row, $viewer))
            ->take($limit)
            ->map(fn (RolloutGateApprovalRequest $row) => $this->presentRequest($row, $viewer))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRequest(RolloutGateApprovalRequest $request, ?TenantUser $viewer = null): array
    {
        $program = $request->rolloutProgram;
        $phase = $request->timelinePhase;
        $currentRole = $request->currentApproverRole();
        $canAct = $this->viewerCanAct($request, $viewer);
        $actingFor = $canAct && $viewer !== null && $program !== null && $currentRole !== null
            ? $this->approvers->actingForDelegator($viewer, $program, $currentRole)
            : null;
        $stepWaitingDays = null;
        $escalationDue = false;

        if ($request->isOpen() && $program !== null && $request->current_step_started_at !== null) {
            $stepWaitingDays = $this->calendarFactory
                ->make($program->region)
                ->workingDaysBetween(
                    Carbon::parse($request->current_step_started_at)->startOfDay(),
                    Carbon::today(),
                );
            $threshold = $this->escalation->escalationWorkingDays();
            $escalationDue = $stepWaitingDays >= $threshold
                && ($request->last_escalated_at === null
                    || Carbon::parse($request->last_escalated_at)->lt($request->current_step_started_at));
        }

        return [
            'id' => $request->id,
            'status' => $request->status,
            'phase_key' => $request->phase_key,
            'gate_label' => $request->gate_label,
            'current_step' => $request->current_step,
            'current_approver_role' => $currentRole,
            'approval_chain' => $request->approval_chain,
            'step_log' => $request->step_log ?? [],
            'request_notes' => $request->request_notes,
            'rejection_notes' => $request->rejection_notes,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'current_step_started_at' => $request->current_step_started_at?->toIso8601String(),
            'last_escalated_at' => $request->last_escalated_at?->toIso8601String(),
            'step_waiting_working_days' => $stepWaitingDays,
            'escalation_due' => $escalationDue,
            'completed_at' => $request->completed_at?->toIso8601String(),
            'can_act' => $canAct,
            'acting_for' => $actingFor,
            'rollout' => $program ? [
                'id' => $program->id,
                'rollout_ref' => $program->rollout_ref,
                'search_ring_name' => $program->search_ring_name,
            ] : null,
            'phase' => $phase ? [
                'id' => $phase->id,
                'label' => $phase->label,
            ] : null,
            'requested_by' => $request->requestedBy ? [
                'id' => $request->requestedBy->id,
                'name' => $request->requestedBy->name,
            ] : null,
        ];
    }

    public function activeRequestForPhase(RolloutTimelinePhase $phase): ?RolloutGateApprovalRequest
    {
        return RolloutGateApprovalRequest::query()
            ->where('rollout_timeline_phase_id', $phase->id)
            ->where('status', RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->latest('submitted_at')
            ->first();
    }

    public function latestRequestForPhase(RolloutTimelinePhase $phase): ?RolloutGateApprovalRequest
    {
        return RolloutGateApprovalRequest::query()
            ->where('rollout_timeline_phase_id', $phase->id)
            ->latest('submitted_at')
            ->first();
    }

    private function assertProgramEditable(RolloutProgram $program): void
    {
        if (in_array($program->status, ['completed', 'cancelled', 'batch'], true)) {
            throw ValidationException::withMessages([
                'rollout' => [__('This rollout cannot be edited.')],
            ]);
        }
    }

    private function viewerCanAct(RolloutGateApprovalRequest $request, ?TenantUser $viewer): bool
    {
        if ($viewer === null || ! $request->isOpen()) {
            return false;
        }

        $program = $request->rolloutProgram;
        $currentRole = $request->currentApproverRole();

        return $program !== null
            && $currentRole !== null
            && $this->approvers->canActOnStep($viewer, $program, $currentRole);
    }
}
