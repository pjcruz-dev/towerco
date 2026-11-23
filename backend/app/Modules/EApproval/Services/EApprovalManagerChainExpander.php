<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Illuminate\Validation\ValidationException;

/**
 * Turns one Direct manager step into sequential approvers along the Entra reporting line.
 */
final class EApprovalManagerChainExpander
{
    /** @var array<string, list<array{user_id: string, role_label: string}>> */
    private array $resolvedByKey = [];

    public function __construct(
        private readonly EApprovalManagerApproverResolver $managers,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array<string, mixed>>
     */
    public function expand(array $definitions, ?string $requestorEmail, bool $strict): array
    {
        $ordered = [];
        foreach (array_values($definitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $ordered[] = ['index' => $index, 'definition' => $definition];
        }
        usort($ordered, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['definition']['step_order'] ?? 0);
            $rightOrder = (int) ($right['definition']['step_order'] ?? 0);

            return $leftOrder <=> $rightOrder ?: $left['index'] <=> $right['index'];
        });

        $expanded = [];
        $shift = 0;
        $lastOriginal = null;
        $lastEmitted = 0;

        foreach ($ordered as $row) {
            $definition = $row['definition'];
            $original = (int) ($definition['step_order'] ?? 1);
            if ($lastOriginal !== null && $original === $lastOriginal) {
                $assigned = $lastEmitted;
            } else {
                $assigned = $original + $shift;
            }

            $members = $this->expandOne($definition, $requestorEmail, $strict);
            if ($members === null) {
                $definition['step_order'] = $assigned;
                $expanded[] = $definition;
                $lastEmitted = $assigned;
                $lastOriginal = $original;

                continue;
            }

            foreach ($members as $offset => $member) {
                $member['step_order'] = $assigned + $offset;
                $expanded[] = $member;
            }
            $extra = count($members) - 1;
            if ($extra > 0) {
                $shift += $extra;
            }
            $lastEmitted = $assigned + max(0, $extra);
            $lastOriginal = $original;
        }

        return $expanded;
    }

    /**
     * Remember the line that was just resolved so a submission snapshot can show it
     * without calling Entra again.
     *
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array<string, mixed>>
     */
    public function stamp(array $definitions): array
    {
        $stamped = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $key = $this->chainKey($definition);
            if ($key !== null && isset($this->resolvedByKey[$key]) && $this->resolvedByKey[$key] !== []) {
                $definition['resolved_chain'] = $this->resolvedByKey[$key];
            }
            $stamped[] = $definition;
        }

        return $stamped;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>>|null null keeps the original step
     */
    private function expandOne(array &$definition, ?string $requestorEmail, bool $strict): ?array
    {
        $type = EApprovalFormPolicySupport::normalizeApproverType(
            (string) ($definition['type'] ?? $definition['approver_type'] ?? 'user'),
        );
        if ($type !== 'manager' || ! EApprovalWorkflowStepDefinitionSupport::managerChainEnabled($definition)) {
            return null;
        }

        $frozen = $this->frozenPeople($definition);
        if ($frozen !== []) {
            return $this->membersFor($definition, $frozen);
        }

        $settings = EApprovalWorkflowStepDefinitionSupport::managerChainSettings($definition);
        if ($settings === null) {
            return null;
        }

        $email = trim((string) $requestorEmail);
        if ($email === '') {
            if ($strict) {
                throw ValidationException::withMessages([
                    'workflow' => [__('Enter the requestor email to resolve the reporting line.')],
                ]);
            }

            return null;
        }

        $resolution = $this->managers->resolveChain(
            trim((string) $requestorEmail),
            $settings['stop_titles'],
            $settings['max_levels'],
        );

        if ($resolution->failure !== null) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'workflow' => [$resolution->failure],
                ]);
            }

            $definition['manager_chain_warning'] = $resolution->failure;

            return null;
        }

        if ($resolution->people === []) {
            return null;
        }

        $key = $this->chainKey($definition);
        if ($key !== null) {
            $this->resolvedByKey[$key] = $resolution->people;
        }

        return $this->membersFor($definition, $resolution->people);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{user_id: string, role_label: string}>
     */
    private function frozenPeople(array $definition): array
    {
        $raw = $definition['resolved_chain'] ?? null;
        if (! is_array($raw)) {
            $condition = is_array($definition['condition'] ?? null) ? $definition['condition'] : [];
            $raw = $condition['resolved_chain'] ?? null;
        }
        if (! is_array($raw)) {
            return [];
        }

        $people = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $userId = trim((string) ($row['user_id'] ?? ''));
            if ($userId === '') {
                continue;
            }
            $role = trim((string) ($row['role_label'] ?? ''));
            $people[] = [
                'user_id' => $userId,
                'role_label' => $role !== '' ? mb_substr($role, 0, 120) : 'Manager',
            ];
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<array{user_id: string, role_label: string}>  $people
     * @return list<array<string, mixed>>
     */
    private function membersFor(array $definition, array $people): array
    {
        $customLabel = trim((string) ($definition['label'] ?? ''));
        $members = [];
        foreach ($people as $person) {
            $member = $definition;
            unset(
                $member['manager_chain'],
                $member['manager_chain_stop_titles'],
                $member['manager_chain_max_levels'],
                $member['resolved_chain'],
                $member['manager_chain_warning'],
            );
            $role = $person['role_label'];
            $member['type'] = 'user';
            $member['approverId'] = $person['user_id'];
            $member['manager_chain_role'] = $role;
            $member['label'] = $customLabel !== '' ? $customLabel : $role;
            if (is_array($member['condition'] ?? null)) {
                unset(
                    $member['condition']['manager_chain'],
                    $member['condition']['manager_chain_stop_titles'],
                    $member['condition']['manager_chain_max_levels'],
                    $member['condition']['resolved_chain'],
                );
            }
            $members[] = $member;
        }

        return $members;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function chainKey(array $definition): ?string
    {
        $settings = EApprovalWorkflowStepDefinitionSupport::managerChainSettings($definition);
        if ($settings === null) {
            return null;
        }

        return json_encode([$settings['stop_titles'], $settings['max_levels']]);
    }
}
