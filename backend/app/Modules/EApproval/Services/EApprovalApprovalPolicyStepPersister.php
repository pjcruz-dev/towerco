<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EApprovalApprovalPolicyStepPersister
{
    /**
     * @param  list<array<string, mixed>>  $stepDefinitions
     * @return Collection<int, EApprovalWorkflowStep>
     */
    public function persist(string $templateId, string $submissionId, array $stepDefinitions): Collection
    {
        EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->delete();

        $persisted = collect();

        foreach (array_values($stepDefinitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $type = match (strtolower(trim((string) ($definition['type'] ?? $definition['approver_type'] ?? 'user')))) {
                'fixed', 'fixed_user', 'fixeduser' => 'user',
                'approver_field', 'from_field', 'from_approver_field' => 'field',
                'direct_manager', 'entra_manager' => 'manager',
                'field_map', 'map_field', 'mapped_field' => 'field_map',
                default => strtolower(trim((string) ($definition['type'] ?? $definition['approver_type'] ?? 'user'))),
            };
            $approverId = isset($definition['approverId'])
                ? trim((string) $definition['approverId'])
                : trim((string) ($definition['approver_id'] ?? ''));
            $condition = is_array($definition['condition'] ?? null) ? $definition['condition'] : null;
            $when = EApprovalWorkflowStepDefinitionSupport::whenFromDefinition(
                $definition,
                is_array($condition) ? $condition : [],
            );

            if ($type === 'field_map') {
                $sourceField = trim((string) ($definition['source_field'] ?? $approverId));
                $approverId = $sourceField !== '' ? $sourceField : null;
                $condition = [
                    'mappings' => is_array($definition['mappings'] ?? null) ? $definition['mappings'] : [],
                    'default_approver_id' => $definition['default_approver_id'] ?? null,
                ];
            }

            if ($when !== []) {
                $condition = is_array($condition) ? $condition : [];
                $condition['when'] = $when;
            }

            $approverId = $approverId === '' ? null : $approverId;

            $step = EApprovalWorkflowStep::query()->create([
                'id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'step_order' => (int) ($definition['step_order'] ?? $index + 1),
                'approver_type' => $type,
                'approver_id' => $approverId,
                'condition' => $condition,
                'compiled_for_submission_id' => $submissionId,
            ]);

            $persisted->push($step);
        }

        return $persisted->sortBy('step_order')->values();
    }
}
