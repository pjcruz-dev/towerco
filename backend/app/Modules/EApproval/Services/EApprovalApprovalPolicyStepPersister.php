<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\EApproval\Support\EApprovalParallelMode;
use App\Modules\EApproval\Support\EApprovalReviewStep;
use App\Modules\EApproval\Support\EApprovalWhenLogic;
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
        // Approvals FK cascade-delete on step removal. Keep any compiled step still
        // referenced by an approval so revision history / resume routing survive recompile.
        $referencedStepIds = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->whereNotNull('step_id')
            ->pluck('step_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->when(
                $referencedStepIds !== [],
                static fn ($query) => $query->whereNotIn('id', $referencedStepIds),
            )
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
                'user_list', 'field_list', 'approver_list', 'from_approver_list' => 'user_list',
                'reviewer_list', 'reviewers', 'from_reviewer_list' => 'reviewer_list',
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

            $fallback = $definition['fallback_approver_id'] ?? (is_array($condition) ? ($condition['fallback_approver_id'] ?? null) : null);
            if (is_string($fallback) && trim($fallback) !== '') {
                $condition = is_array($condition) ? $condition : [];
                $condition['fallback_approver_id'] = trim($fallback);
            }

            $mode = EApprovalParallelMode::normalize(
                isset($definition['parallel_mode'])
                    ? (string) $definition['parallel_mode']
                    : (is_array($condition) && isset($condition['parallel_mode']) ? (string) $condition['parallel_mode'] : null),
            );
            if ($mode === EApprovalParallelMode::ALL) {
                if (is_array($condition)) {
                    unset($condition['parallel_mode'], $condition['parallel_quorum']);
                    if ($condition === []) {
                        $condition = null;
                    }
                }
            } else {
                $condition = is_array($condition) ? $condition : [];
                $condition['parallel_mode'] = $mode;
                if ($mode === EApprovalParallelMode::N_OF_M) {
                    $raw = $definition['parallel_quorum'] ?? $condition['parallel_quorum'] ?? 1;
                    $condition['parallel_quorum'] = max(1, (int) $raw);
                } else {
                    unset($condition['parallel_quorum']);
                }
            }

            if ($when !== []) {
                $condition = is_array($condition) ? $condition : [];
                $condition['when'] = $when;
                $logic = EApprovalWhenLogic::fromDefinition($definition, $condition);
                if ($logic === EApprovalWhenLogic::OR) {
                    $condition['when_logic'] = EApprovalWhenLogic::OR;
                } else {
                    unset($condition['when_logic']);
                }
            } elseif (is_array($condition)) {
                unset($condition['when'], $condition['when_logic']);
            }

            if (EApprovalReviewStep::isReviewDefinition($definition)) {
                $condition = is_array($condition) ? $condition : [];
                $condition['duty'] = EApprovalReviewStep::DUTY;
            }

            $optional = ! empty($definition['optional'])
                || (is_array($definition['condition'] ?? null) && ! empty($definition['condition']['optional']));
            if ($optional) {
                $condition = is_array($condition) ? $condition : [];
                $condition['optional'] = true;
            }

            $approverId = $approverId === '' ? null : $approverId;

            $mail = EApprovalFormMailConfig::normalizeMail(
                is_array($definition['mail'] ?? null) ? $definition['mail'] : null,
            );
            if (! EApprovalFormMailConfig::isDefaultMail($mail)) {
                $condition = is_array($condition) ? $condition : [];
                $condition['mail'] = $mail;
            }
            $label = EApprovalFormMailConfig::cleanPublicLabel($definition['label'] ?? '');

            $step = EApprovalWorkflowStep::query()->create([
                'id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'step_order' => (int) ($definition['step_order'] ?? $index + 1),
                'approver_type' => $type,
                'approver_id' => $approverId,
                'label' => $label !== '' ? $label : null,
                'condition' => $condition,
                'compiled_for_submission_id' => $submissionId,
            ]);

            $persisted->push($step);
        }

        return $persisted->sortBy('step_order')->values();
    }
}
