<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynEntityHook;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynEntityHookDsl;
use App\Modules\Identity\Models\TenantUser;

/**
 * Runs declarative entity hooks on DynRecord lifecycle events.
 */
final class DynEntityHookRunner
{
    private static int $depth = 0;

    public function __construct(
        private readonly DynEntityHookService $hooks,
    ) {}

    /**
     * Mutate values in-place for before_create / before_update.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $beforeValues
     * @return array<string, mixed>
     */
    public function applyBefore(
        DynEntity $entity,
        array $values,
        TenantUser $actor,
        string $event,
        ?array $beforeValues = null,
        ?string $recordId = null,
    ): array {
        if (! in_array($event, ['before_create', 'before_update', 'before_action', 'before_delete'], true)) {
            return $values;
        }

        return $this->runMutations(
            (string) $entity->slug,
            $values,
            $actor,
            $event,
            $beforeValues,
            $recordId,
        );
    }

    public function afterCreate(DynEntity $entity, DynRecord $record, TenantUser $actor): void
    {
        $this->runAfter($entity, $record, $actor, 'after_create', null);
    }

    public function afterUpdate(
        DynEntity $entity,
        DynRecord $record,
        TenantUser $actor,
        ?array $beforeValues,
    ): void {
        $this->runAfter($entity, $record, $actor, 'after_update', $beforeValues);
    }

    public function afterDelete(DynEntity $entity, DynRecord $record, TenantUser $actor): void
    {
        $this->runAfter($entity, $record, $actor, 'after_delete', null);
    }

    /**
     * @param  array<string, mixed>|null  $beforeValues
     */
    private function runAfter(
        DynEntity $entity,
        DynRecord $record,
        TenantUser $actor,
        string $event,
        ?array $beforeValues,
    ): void {
        if (self::$depth > 0) {
            return;
        }

        $list = $this->hooks->activeFor((string) $entity->slug, $event);
        if ($list === []) {
            return;
        }

        self::$depth++;
        try {
            $values = is_array($record->values_json) ? $record->values_json : [];
            $mutated = $this->runMutations(
                (string) $entity->slug,
                $values,
                $actor,
                $event,
                $beforeValues,
                (string) $record->id,
                $list,
            );
            if ($mutated === $values) {
                return;
            }
            // Persist value mutations without re-entering record service hooks.
            $record->values_json = $mutated;
            if (isset($mutated['status'])) {
                $record->status = (string) $mutated['status'];
            }
            $record->updated_by = $actor->id;
            $record->save();
        } catch (\Throwable) {
            // After hooks must not fail the parent save.
        } finally {
            self::$depth = max(0, self::$depth - 1);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $beforeValues
     * @param  list<DynEntityHook>|null  $preloaded
     * @return array<string, mixed>
     */
    private function runMutations(
        string $entitySlug,
        array $values,
        TenantUser $actor,
        string $event,
        ?array $beforeValues,
        ?string $recordId,
        ?array $preloaded = null,
    ): array {
        $list = $preloaded ?? $this->hooks->activeFor($entitySlug, $event);
        if ($list === []) {
            return $values;
        }

        $ctx = [
            'actor_id' => (string) $actor->id,
            'record_id' => $recordId,
        ];

        $out = $values;
        foreach ($list as $hook) {
            if (! $hook instanceof DynEntityHook) {
                continue;
            }
            $definition = DynEntityHookDsl::normalize(
                is_array($hook->definition_json) ? $hook->definition_json : null,
            );
            if ($definition['actions'] === []) {
                continue;
            }
            if (! DynEntityHookDsl::matchesWhen($definition, $out, $beforeValues)) {
                continue;
            }
            $out = DynEntityHookDsl::apply($definition, $out, $ctx);
        }

        return $out;
    }
}
