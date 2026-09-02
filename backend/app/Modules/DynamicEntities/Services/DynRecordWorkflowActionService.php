<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class DynRecordWorkflowActionService
{
    public function __construct(
        private readonly DynRecordService $records,
        private readonly TenantActivityLogger $activity,
        private readonly DynEmailTemplateService $emailTemplates,
    ) {}

    /**
     * Apply a registered workflow GET / WHEN / THEN action. Caller must enforce ACL.
     *
     * @return array<string, mixed>
     */
    public function run(DynRecord $record, string $actionId, TenantUser $actor): array
    {
        $record->loadMissing('entity.fields');
        $entity = $record->entity;
        abort_unless($entity !== null, 404);

        $slug = (string) $entity->slug;
        $action = DynEntityWorkflowActions::find($entity, $actionId);
        if ($action === null) {
            throw ValidationException::withMessages([
                'action' => 'Unknown workflow action.',
            ]);
        }

        $roleIds = $action['role_ids'] ?? [];
        if (is_array($roleIds) && $roleIds !== []) {
            $actorRoleIds = $actor->roles->map(static fn ($r) => (string) $r->id)->all();
            $allowed = array_intersect($roleIds, $actorRoleIds) !== [];
            abort_unless($allowed, 403, __('Your role cannot press this workflow button.'));
        }

        $values = is_array($record->values_json) ? $record->values_json : [];
        if (! DynEntityWorkflowActions::matchesWhen($action, $record->status, $values)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    '“%s” is not available for the current record values.',
                    $action['label'],
                ),
            ]);
        }

        $context = $this->buildContext($entity, $record, $values, $action['loads'] ?? []);

        $beforeStatus = trim((string) ($record->status ?? ''));
        $thisPatchValues = [];
        $nextStatus = null;
        $relatedPatches = [];

        foreach ($action['then_updates'] as $upd) {
            $target = (string) ($upd['target'] ?? 'this');
            $field = (string) ($upd['field'] ?? '');
            $mode = (string) ($upd['mode'] ?? 'set');
            if ($field === '' || ! in_array($mode, ['set', 'copy'], true)) {
                continue;
            }
            $resolved = $this->resolveValue($upd, $context);
            if ($target === 'this') {
                $thisPatchValues[$field] = $resolved;
                if ($field === 'status') {
                    $nextStatus = $resolved;
                }
            } else {
                $relatedPatches[$target][$field] = $resolved;
            }
        }

        if ($nextStatus === null && isset($action['to_status']) && (string) $action['to_status'] !== '') {
            $nextStatus = (string) $action['to_status'];
            $thisPatchValues['status'] = $nextStatus;
        }

        $payload = ['values' => $thisPatchValues];
        if ($nextStatus !== null) {
            $payload['status'] = $nextStatus;
        }

        $updated = $this->records->update($record, $payload, $actor, audit: false);

        foreach ($relatedPatches as $alias => $patch) {
            $loaded = $context[$alias]['record'] ?? null;
            if (! $loaded instanceof DynRecord) {
                continue;
            }
            $relatedPayload = ['values' => $patch];
            if (isset($patch['status'])) {
                $relatedPayload['status'] = $patch['status'];
            }
            $this->records->update($loaded, $relatedPayload, $actor, audit: false);
        }

        $createdIds = $this->runCreates($action['creates'] ?? [], $context, $actor);
        $emailsSent = $this->runEmails($action['emails'] ?? [], $context, $actor);

        // Refresh context this after update for audit clarity.
        $title = trim((string) ($updated->title ?? ''));
        $label = $title !== '' ? $entity->name.' · '.$title : $entity->name.' · '.substr((string) $updated->id, 0, 8);

        $changes = [];
        foreach ($thisPatchValues as $field => $to) {
            $from = $field === 'status'
                ? $beforeStatus
                : (is_scalar($values[$field] ?? null) ? (string) $values[$field] : null);
            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_record.workflow_'.$actionId,
            summary: $action['label'].' · '.$label,
            entityType: 'dyn_record',
            entityId: (string) $updated->id,
            entityLabel: $label,
            actor: $actor,
            metadata: [
                'entity_slug' => $slug,
                'entity_name' => (string) $entity->name,
                'workflow_action' => $actionId,
                'created_record_ids' => $createdIds,
                'emails_sent' => $emailsSent,
            ],
            changes: $changes,
        );

        return $this->records->presentDetail($entity, $updated->fresh());
    }

    /**
     * @param  list<array{source_field: string, alias: string}>  $loads
     * @param  array<string, mixed>  $values
     * @return array<string, array{values: array<string, mixed>, status: ?string, title: ?string, record?: DynRecord, email?: ?string}>
     */
    private function buildContext(DynEntity $entity, DynRecord $record, array $values, array $loads): array
    {
        $context = [
            'this' => [
                'values' => $values,
                'status' => $record->status,
                'title' => $record->title,
                'record' => $record,
                'email' => null,
            ],
        ];

        $entity->loadMissing('fields');
        foreach ($loads as $load) {
            $alias = (string) ($load['alias'] ?? '');
            $sourceField = (string) ($load['source_field'] ?? '');
            if ($alias === '' || $sourceField === '') {
                continue;
            }
            $rawId = $values[$sourceField] ?? null;
            if (! is_string($rawId) || trim($rawId) === '') {
                continue;
            }
            $related = DynRecord::query()
                ->with('entity')
                ->whereKey($rawId)
                ->where('is_deleted', false)
                ->first();
            if (! $related) {
                continue;
            }
            $relatedValues = is_array($related->values_json) ? $related->values_json : [];
            $context[$alias] = [
                'values' => $relatedValues,
                'status' => $related->status,
                'title' => $related->title,
                'record' => $related,
                'email' => is_scalar($relatedValues['email'] ?? null) ? (string) $relatedValues['email'] : null,
            ];
        }

        return $context;
    }

    /**
     * @param  array{mode?: string, value?: string, from?: string}  $spec
     * @param  array<string, array{values: array<string, mixed>, status: ?string, title: ?string}>  $context
     */
    private function resolveValue(array $spec, array $context): string
    {
        $mode = (string) ($spec['mode'] ?? 'set');
        if ($mode === 'copy') {
            $from = trim((string) ($spec['from'] ?? ''));
            if ($from === '') {
                return '';
            }
            [$alias, $field] = array_pad(explode('.', $from, 2), 2, '');
            if ($field === '') {
                $field = $alias;
                $alias = 'this';
            }
            $bag = $context[$alias] ?? null;
            if ($bag === null) {
                return '';
            }
            if ($field === 'status') {
                return trim((string) ($bag['status'] ?? '')) ?: 'Draft';
            }
            if ($field === 'title') {
                return trim((string) ($bag['title'] ?? ''));
            }

            return DynEntityWorkflowActions::fieldValue($field, $bag['status'] ?? null, $bag['values'] ?? []);
        }

        return trim((string) ($spec['value'] ?? ''));
    }

    /**
     * @param  list<array{entity_slug: string, one_per: ?string, mappings: list<array{field: string, mode: string, value: string, from?: string}>}>  $creates
     * @param  array<string, array{values: array<string, mixed>, status: ?string, title: ?string, record?: DynRecord}>  $context
     * @return list<string>
     */
    private function runCreates(array $creates, array $context, TenantUser $actor): array
    {
        $ids = [];
        foreach ($creates as $create) {
            $targetSlug = (string) ($create['entity_slug'] ?? '');
            if ($targetSlug === '') {
                continue;
            }
            $targetEntity = DynEntity::query()->where('slug', $targetSlug)->where('is_active', true)->first();
            if (! $targetEntity) {
                continue;
            }

            $onePer = $create['one_per'] ?? null;
            $sources = [null];
            if (is_string($onePer) && $onePer !== '' && isset($context[$onePer])) {
                $sources = [$onePer];
            }

            foreach ($sources as $_sourceAlias) {
                $values = [];
                $status = null;
                foreach ($create['mappings'] ?? [] as $mapping) {
                    $field = (string) ($mapping['field'] ?? '');
                    if ($field === '') {
                        continue;
                    }
                    $resolved = $this->resolveValue($mapping, $context);
                    $values[$field] = $resolved;
                    if ($field === 'status') {
                        $status = $resolved;
                    }
                }
                try {
                    $created = $this->records->create($targetEntity, [
                        'values' => $values,
                        'status' => $status,
                        'parent_record_id' => ($context['this']['record'] ?? null)?->id,
                    ], $actor);
                    $ids[] = (string) $created->id;
                } catch (\Throwable) {
                    // Incomplete mappings or required fields — do not fail the primary THEN updates.
                }
            }
        }

        return $ids;
    }

    /**
     * @param  list<array{subject?: string, to?: string, body?: string, template_slug?: string}>  $emails
     * @param  array<string, array{values: array<string, mixed>, status: ?string, title: ?string, email?: ?string}>  $context
     */
    private function runEmails(array $emails, array $context, TenantUser $actor): int
    {
        $sent = 0;
        foreach ($emails as $email) {
            $templateSlug = trim((string) ($email['template_slug'] ?? ''));
            $subject = (string) ($email['subject'] ?? 'Workflow notification');
            $body = (string) ($email['body'] ?? '');
            $to = (string) ($email['to'] ?? '');

            if ($templateSlug !== '') {
                $template = $this->emailTemplates->findBySlug($templateSlug);
                if ($template) {
                    $subject = (string) $template->subject;
                    $body = (string) ($template->body_text ?: $template->body_html);
                    if ($to === '' && is_string($template->default_to) && $template->default_to !== '') {
                        $to = (string) $template->default_to;
                    }
                }
            }

            $to = $this->emailTemplates->merge($to, $context, $actor);
            $subject = $this->emailTemplates->merge($subject, $context, $actor);
            $body = $this->emailTemplates->merge($body, $context, $actor);
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::raw($body !== '' ? $body : $subject, static function ($message) use ($to, $subject): void {
                    $message->to($to)->subject($subject);
                });
                $sent++;
            } catch (\Throwable) {
                // Mail may be unconfigured in staging; do not fail the workflow.
            }
        }

        return $sent;
    }
}
