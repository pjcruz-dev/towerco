<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Models\DynWorkflow;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use App\Modules\Identity\Models\TenantUser;

/**
 * Runs managed workflows with trigger_mode on_create / on_update.
 */
final class DynWorkflowAutoRunner
{
    private static int $depth = 0;

    public function __construct(
        private readonly DynWorkflowService $workflows,
        private readonly DynRecordWorkflowActionService $actions,
    ) {}

    public function afterCreate(DynEntity $entity, DynRecord $record, TenantUser $actor): void
    {
        $this->runMatching($entity, $record, $actor, 'on_create', null);
    }

    public function afterUpdate(
        DynEntity $entity,
        DynRecord $record,
        TenantUser $actor,
        ?string $beforeStatus,
    ): void {
        $this->runMatching($entity, $record, $actor, 'on_update', $beforeStatus);
    }

    private function runMatching(
        DynEntity $entity,
        DynRecord $record,
        TenantUser $actor,
        string $trigger,
        ?string $beforeStatus,
    ): void {
        if (self::$depth > 0) {
            return;
        }

        $list = $this->workflows->autoWorkflows((string) $entity->slug, $trigger);
        if ($list === []) {
            return;
        }

        $values = is_array($record->values_json) ? $record->values_json : [];
        self::$depth++;
        try {
            foreach ($list as $workflow) {
                if (! $workflow instanceof DynWorkflow) {
                    continue;
                }
                $action = $this->workflows->toActionDef($workflow);
                if (! DynEntityWorkflowActions::matchesWhen($action, $record->status, $values)) {
                    continue;
                }
                // on_update: only when status (or tracked field) newly matches.
                if ($trigger === 'on_update') {
                    $field = (string) ($workflow->status_field ?: 'status');
                    if ($field === 'status') {
                        $prev = trim((string) ($beforeStatus ?? ''));
                        $curr = DynEntityWorkflowActions::fieldValue($field, $record->status, $values);
                        $matches = is_array($workflow->status_matches) ? $workflow->status_matches : [];
                        if ($matches !== [] && $prev !== '' && strcasecmp($prev, $curr) === 0) {
                            continue;
                        }
                    }
                }

                try {
                    $this->actions->run($record->fresh(['entity.fields']) ?? $record, (string) $workflow->slug, $actor);
                    $record->refresh();
                    $values = is_array($record->values_json) ? $record->values_json : [];
                } catch (\Throwable) {
                    // Auto workflows should not fail the parent save.
                    continue;
                }
            }
        } finally {
            self::$depth = max(0, self::$depth - 1);
        }
    }
}
