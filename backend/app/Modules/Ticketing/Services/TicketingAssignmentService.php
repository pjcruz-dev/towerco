<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use Illuminate\Validation\ValidationException;

final class TicketingAssignmentService
{
    public function __construct(
        private readonly TicketingSettingsService $settings,
        private readonly TicketingCategoryCatalog $categories,
    ) {}

    /**
     * Resolve default assignee for a category when the creator left assignee empty.
     */
    public function resolveAssigneeId(?string $category): ?string
    {
        if ($category === null || trim($category) === '') {
            return null;
        }

        $slug = strtolower(trim($category));
        foreach ($this->settings->assignmentRules() as $rule) {
            if (! ($rule['enabled'] ?? true)) {
                continue;
            }
            if (($rule['category'] ?? '') !== $slug) {
                continue;
            }

            $assigneeId = (string) ($rule['assignee_id'] ?? '');
            if ($assigneeId === '') {
                return null;
            }

            $user = TenantUser::query()->whereKey($assigneeId)->where('is_active', true)->first();
            if (! $user instanceof TenantUser) {
                return null;
            }

            $pool = $this->settings->itAssigneeUserIds();
            if ($pool !== null && ! in_array((string) $user->id, $pool, true)) {
                return null;
            }

            return (string) $user->id;
        }

        return null;
    }

    /**
     * Soft parse for settings reads — drops unknown categories / inactive users.
     *
     * @param  list<mixed>  $raw
     * @return list<array{category: string, assignee_id: string, enabled: bool}>
     */
    public function parseStoredRules(array $raw): array
    {
        return $this->normalizeRules($raw, strict: false);
    }

    /**
     * Strict normalize for settings writes.
     *
     * @param  list<mixed>  $raw
     * @param  list<string>|null  $poolOverride  When set (including []), use instead of stored pool.
     * @return list<array{category: string, assignee_id: string, enabled: bool}>
     */
    public function normalizeRulesForPersist(
        array $raw,
        bool $dropOutOfPool = false,
        ?array $poolOverride = null,
    ): array {
        return $this->normalizeRules(
            $raw,
            strict: true,
            dropOutOfPool: $dropOutOfPool,
            poolOverride: $poolOverride,
        );
    }

    /**
     * @param  list<mixed>  $raw
     * @param  list<string>|null  $poolOverride
     * @return list<array{category: string, assignee_id: string, enabled: bool}>
     */
    private function normalizeRules(
        array $raw,
        bool $strict,
        bool $dropOutOfPool = false,
        ?array $poolOverride = null,
    ): array {
        $validCategories = array_flip($this->categories->resolve());
        $rules = [];
        $seen = [];
        $pool = $poolOverride !== null ? $poolOverride : $this->settings->itAssigneeUserIds();

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $category = strtolower(trim((string) ($item['category'] ?? '')));
            if ($category === '' || ! isset($validCategories[$category]) || isset($seen[$category])) {
                continue;
            }

            $assigneeId = trim((string) ($item['assignee_id'] ?? ''));
            if ($assigneeId === '') {
                continue;
            }

            $active = TenantUser::query()->whereKey($assigneeId)->where('is_active', true)->exists();
            if (! $active) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'assignment_rules' => [__('Assignee for :category must be an active user.', ['category' => $category])],
                    ]);
                }

                continue;
            }

            if ($pool !== null && ! in_array($assigneeId, $pool, true)) {
                if ($strict && ! $dropOutOfPool) {
                    throw ValidationException::withMessages([
                        'assignment_rules' => [__('Assignee for :category must be in the IT assignee list.', ['category' => $category])],
                    ]);
                }

                continue;
            }

            $seen[$category] = true;
            $rules[] = [
                'category' => $category,
                'assignee_id' => $assigneeId,
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $rules;
    }
}
