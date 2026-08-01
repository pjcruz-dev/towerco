<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\EApproval\Support\EApprovalParallelMode;
use App\Modules\EApproval\Support\EApprovalUserListValueParser;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Expands user_list workflow steps into fixed-user parallel siblings from a form field.
 */
final class EApprovalUserListStepExpander
{
    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    public function expand(array $definitions, array $values, bool $strict = true): array
    {
        $expanded = [];

        foreach (array_values($definitions) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $type = EApprovalFormPolicySupport::normalizeApproverType(
                (string) ($definition['type'] ?? $definition['approver_type'] ?? 'user'),
            );

            if ($type !== 'user_list') {
                $expanded[] = $definition;

                continue;
            }

            $members = $this->expandOne($definition, $values, $strict);
            if ($members === []) {
                // Soft preview mode: keep the template step so the UI can warn.
                $expanded[] = $definition;

                continue;
            }

            foreach ($members as $member) {
                $expanded[] = $member;
            }
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function expandOne(array $definition, array $values, bool $strict): array
    {
        $fieldName = trim((string) ($definition['approverId'] ?? $definition['approver_id'] ?? ''));
        $order = (int) ($definition['step_order'] ?? 1);
        $when = EApprovalWorkflowStepDefinitionSupport::whenFromDefinition(
            $definition,
            is_array($definition['condition'] ?? null) ? $definition['condition'] : [],
        );

        $mode = EApprovalParallelMode::normalize(
            isset($definition['parallel_mode'])
                ? (string) $definition['parallel_mode']
                : (is_array($definition['condition'] ?? null) && isset($definition['condition']['parallel_mode'])
                    ? (string) $definition['condition']['parallel_mode']
                    : null),
        );
        $quorumRaw = $definition['parallel_quorum']
            ?? (is_array($definition['condition'] ?? null) ? ($definition['condition']['parallel_quorum'] ?? null) : null);

        $fallback = $definition['fallback_approver_id']
            ?? (is_array($definition['condition'] ?? null) ? ($definition['condition']['fallback_approver_id'] ?? null) : null);
        $fallbackId = is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;

        if ($fieldName === '') {
            if (! $strict) {
                return [];
            }

            throw ValidationException::withMessages([
                'workflow' => [__('A dynamic approver list step is missing its source field.')],
            ]);
        }

        $candidates = EApprovalUserListValueParser::parse($values[$fieldName] ?? null);
        $resolved = $this->resolveActiveUserIds($candidates);

        if ($resolved === [] && $fallbackId !== null) {
            $resolved = $this->resolveActiveUserIds([$fallbackId]);
        }

        if ($resolved === []) {
            if (! $strict) {
                return [];
            }

            throw ValidationException::withMessages([
                'workflow' => [__(
                    'Approver list ":field" is empty or has no active users. Add stakeholders or set a fallback approver.',
                    ['field' => $fieldName],
                )],
            ]);
        }

        $members = [];
        foreach ($resolved as $userId) {
            $member = [
                'type' => 'user',
                'approverId' => $userId,
                'step_order' => $order,
            ];
            if ($when !== []) {
                $member['when'] = $when;
            }
            if ($mode !== EApprovalParallelMode::ALL) {
                $member['parallel_mode'] = $mode;
                if ($mode === EApprovalParallelMode::N_OF_M) {
                    $member['parallel_quorum'] = max(1, (int) ($quorumRaw ?? 1));
                }
            }
            $members[] = $member;
        }

        return $members;
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function resolveActiveUserIds(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $ids = array_values(array_filter(
            $candidates,
            static fn (string $value): bool => ! str_contains($value, '@'),
        ));
        $emails = array_values(array_filter(
            $candidates,
            static fn (string $value): bool => str_contains($value, '@'),
        ));

        $idMap = [];
        if ($ids !== []) {
            foreach (
                TenantUser::query()
                    ->whereIn('id', $ids)
                    ->where('is_active', true)
                    ->pluck('id') as $id
            ) {
                $idMap[(string) $id] = (string) $id;
            }
        }

        $emailMap = [];
        if ($emails !== []) {
            $lowerEmails = array_map(static fn (string $email): string => strtolower($email), $emails);
            $users = TenantUser::query()
                ->where('is_active', true)
                ->where(function ($query) use ($lowerEmails): void {
                    foreach ($lowerEmails as $email) {
                        $query->orWhereRaw('LOWER(email) = ?', [$email]);
                    }
                })
                ->get(['id', 'email']);

            foreach ($users as $user) {
                $emailMap[strtolower((string) $user->email)] = (string) $user->id;
            }
        }

        $ordered = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $match = null;
            if (isset($idMap[$candidate])) {
                $match = $idMap[$candidate];
            } elseif (str_contains($candidate, '@')) {
                $match = $emailMap[strtolower($candidate)] ?? null;
            }

            if ($match === null || isset($seen[$match])) {
                continue;
            }

            $seen[$match] = true;
            $ordered[] = $match;
        }

        return $ordered;
    }
}
