<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Support\AssistantProposedActionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Propose (never mutates) and confirm (mutates via allowlisted action + domain service).
 */
final class AssistantActionService
{
    public function __construct(
        private readonly AssistantActionRegistry $registry,
        private readonly AssistantActionRouter $router,
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return array<string, mixed>|null  API-shaped proposed_action or null
     */
    public function maybePropose(
        TenantUser $viewer,
        string $question,
        ?string $moduleContext,
        ?string $conversationId,
        ?string $messageId,
    ): ?array {
        if (! (bool) config('ai_assistant.actions.enabled', true)) {
            return null;
        }

        if (! $viewer->can('ai_assistant:tools:use')) {
            return null;
        }

        $match = $this->router->match($question, $moduleContext);
        if ($match === null) {
            return null;
        }

        try {
            $proposal = $this->propose(
                viewer: $viewer,
                actionName: $match['action'],
                question: $question,
                args: $match['args'],
                conversationId: $conversationId,
                messageId: $messageId,
            );
        } catch (Throwable) {
            return null;
        }

        return $this->asApiPayload($proposal);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function propose(
        TenantUser $viewer,
        string $actionName,
        string $question,
        array $args = [],
        ?string $conversationId = null,
        ?string $messageId = null,
    ): AiAssistantProposedAction {
        abort_unless($viewer->can('ai_assistant:tools:use'), 403);
        abort_unless($this->registry->has($actionName), 422, __('Action is not allowlisted.'));

        $action = $this->registry->get($actionName);
        $this->assertModuleAndDomain($viewer, $action->requiredModule(), $action->requiredDomainPermissions());

        $draft = $action->propose($viewer, $question, $args);

        $ttlMinutes = max(5, (int) config('ai_assistant.actions.proposal_ttl_minutes', 30));

        $proposal = AiAssistantProposedAction::query()->create([
            'user_id' => $viewer->id,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'action' => $action->name(),
            'status' => AssistantProposedActionStatus::PENDING,
            'payload' => $draft->payload,
            'preview' => [
                'title' => $draft->title,
                'summary' => $draft->summary,
                'preview' => $draft->preview,
                'editable_fields' => $draft->editableFields,
                'confirm_label' => $draft->confirmLabel,
                'module_key' => $draft->moduleKey,
            ],
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.action.propose',
            summary: $draft->title,
            entityType: 'ai_assistant_proposed_action',
            entityId: (string) $proposal->id,
            entityLabel: $action->name(),
            actor: $viewer,
            metadata: [
                'action' => $action->name(),
                'payload_keys' => array_keys($draft->payload),
                'conversation_id' => $conversationId,
            ],
        );

        return $proposal;
    }

    /**
     * @param  array<string, mixed>|null  $payloadOverride  Optional field edits from the confirmation card
     * @return array{proposal: AiAssistantProposedAction, result: ActionExecutionResult}
     */
    public function confirm(TenantUser $viewer, string $proposalId, ?array $payloadOverride = null): array
    {
        abort_unless((bool) config('ai_assistant.actions.enabled', true), 403, __('Assistant actions are disabled.'));
        abort_unless($viewer->can('ai_assistant:actions:execute'), 403);
        abort_unless($viewer->can('ai_assistant:tools:use'), 403);

        $proposal = AiAssistantProposedAction::query()->find($proposalId);
        abort_if($proposal === null, 404, __('Proposed action not found.'));
        abort_unless((string) $proposal->user_id === (string) $viewer->id, 403);
        abort_unless($proposal->status === AssistantProposedActionStatus::PENDING, 422, __('This action is no longer pending.'));

        if ($proposal->expires_at !== null && $proposal->expires_at->isPast()) {
            $proposal->forceFill([
                'status' => AssistantProposedActionStatus::EXPIRED,
                'rejection_reason' => 'Proposal expired before confirmation.',
            ])->save();

            $this->auditConfirm($viewer, $proposal, false, 'expired');

            throw ValidationException::withMessages([
                'proposal_id' => [__('This proposed action has expired. Ask again to create a new proposal.')],
            ]);
        }

        abort_unless($this->registry->has($proposal->action), 422, __('Action is not allowlisted.'));
        $action = $this->registry->get($proposal->action);
        $this->assertModuleAndDomain($viewer, $action->requiredModule(), $action->requiredDomainPermissions());

        $payload = is_array($proposal->payload) ? $proposal->payload : [];
        if (is_array($payloadOverride) && $payloadOverride !== []) {
            foreach ($payloadOverride as $key => $value) {
                if (is_string($key)) {
                    $payload[$key] = $value;
                }
            }
        }

        try {
            $validated = Validator::make($payload, $action->argumentRules())->validate();
        } catch (ValidationException $e) {
            $proposal->forceFill([
                'status' => AssistantProposedActionStatus::FAILED,
                'rejection_reason' => 'Validation failed on confirm.',
            ])->save();
            $this->auditConfirm($viewer, $proposal, false, 'validation_failed', [
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        try {
            $result = $action->execute($viewer, $validated);
        } catch (Throwable $e) {
            $proposal->forceFill([
                'status' => AssistantProposedActionStatus::FAILED,
                'rejection_reason' => $e->getMessage(),
            ])->save();
            $this->auditConfirm($viewer, $proposal, false, 'execute_failed', [
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'action' => [$e->getMessage() !== '' ? $e->getMessage() : __('Action execution failed.')],
            ]);
        }

        if (! $result->ok) {
            $proposal->forceFill([
                'status' => AssistantProposedActionStatus::FAILED,
                'rejection_reason' => $result->error ?? 'Action returned not ok.',
            ])->save();
            $this->auditConfirm($viewer, $proposal, false, 'execute_not_ok', [
                'error' => $result->error,
            ]);

            throw ValidationException::withMessages([
                'action' => [$result->error ?? __('Action execution failed.')],
            ]);
        }

        $proposal->forceFill([
            'status' => AssistantProposedActionStatus::CONFIRMED,
            'payload' => $validated,
            'result_entity_type' => $result->entityType,
            'result_entity_id' => $result->entityId,
            'result_meta' => [
                'entity_label' => $result->entityLabel,
                'href' => $result->href,
                'meta' => $result->meta,
            ],
            'confirmed_at' => now(),
            'confirmed_by' => $viewer->id,
            'rejection_reason' => null,
        ])->save();

        $this->auditConfirm($viewer, $proposal, true, 'confirmed', [
            'entity_type' => $result->entityType,
            'entity_id' => $result->entityId,
            'entity_label' => $result->entityLabel,
        ]);

        return [
            'proposal' => $proposal->fresh() ?? $proposal,
            'result' => $result,
        ];
    }

    public function cancel(TenantUser $viewer, string $proposalId): AiAssistantProposedAction
    {
        $proposal = AiAssistantProposedAction::query()->find($proposalId);
        abort_if($proposal === null, 404, __('Proposed action not found.'));
        abort_unless((string) $proposal->user_id === (string) $viewer->id, 403);
        abort_unless($proposal->status === AssistantProposedActionStatus::PENDING, 422, __('This action is no longer pending.'));

        $proposal->forceFill([
            'status' => AssistantProposedActionStatus::CANCELLED,
            'rejection_reason' => 'Cancelled by user.',
        ])->save();

        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.action.cancel',
            summary: $proposal->action,
            entityType: 'ai_assistant_proposed_action',
            entityId: (string) $proposal->id,
            entityLabel: $proposal->action,
            actor: $viewer,
            metadata: ['action' => $proposal->action],
        );

        return $proposal->fresh() ?? $proposal;
    }

    /**
     * @return array<string, mixed>
     */
    public function asApiPayload(AiAssistantProposedAction $proposal): array
    {
        $preview = is_array($proposal->preview) ? $proposal->preview : [];

        return [
            'id' => (string) $proposal->id,
            'action' => $proposal->action,
            'status' => $proposal->status,
            'title' => $preview['title'] ?? $proposal->action,
            'summary' => $preview['summary'] ?? null,
            'payload' => $proposal->payload,
            'preview' => $preview['preview'] ?? [],
            'editable_fields' => $preview['editable_fields'] ?? [],
            'confirm_label' => $preview['confirm_label'] ?? 'Confirm',
            'module_key' => $preview['module_key'] ?? null,
            'expires_at' => $proposal->expires_at?->toIso8601String(),
            'requires_confirmation' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asConfirmResponse(AiAssistantProposedAction $proposal, ActionExecutionResult $result): array
    {
        return [
            'proposal' => [
                'id' => (string) $proposal->id,
                'action' => $proposal->action,
                'status' => $proposal->status,
                'confirmed_at' => $proposal->confirmed_at?->toIso8601String(),
            ],
            'result' => [
                'ok' => $result->ok,
                'entity_type' => $result->entityType,
                'entity_id' => $result->entityId,
                'entity_label' => $result->entityLabel,
                'href' => $result->href,
                'meta' => $result->meta,
            ],
        ];
    }

    /**
     * @param  list<string>  $domainPermissions
     */
    private function assertModuleAndDomain(TenantUser $viewer, ?string $module, array $domainPermissions): void
    {
        if ($module !== null && $module !== '') {
            $enabled = $this->enabledModules->resolveForCurrentTenant();
            if (! in_array($module, $enabled, true)) {
                throw new RuntimeException("Module '{$module}' is not enabled for this tenant.");
            }
        }

        foreach ($domainPermissions as $permission) {
            if (! $viewer->can($permission)) {
                abort(403, __('Missing permission: :permission', ['permission' => $permission]));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function auditConfirm(
        TenantUser $viewer,
        AiAssistantProposedAction $proposal,
        bool $ok,
        string $outcome,
        array $extra = [],
    ): void {
        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.action.confirm',
            summary: $proposal->action,
            entityType: 'ai_assistant_proposed_action',
            entityId: (string) $proposal->id,
            entityLabel: $proposal->action,
            actor: $viewer,
            metadata: [
                'action' => $proposal->action,
                'ok' => $ok,
                'outcome' => $outcome,
                'result_entity_type' => $proposal->result_entity_type,
                'result_entity_id' => $proposal->result_entity_id,
                ...$extra,
            ],
        );
    }
}
