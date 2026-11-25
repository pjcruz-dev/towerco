<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Support\TenantNotificationShell;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TenantNotificationIndexService
{
    /**
     * @param  list<string>  $modules
     */
    public function paginate(
        string $userId,
        array $modules,
        int $page,
        int $perPage,
        ?string $category = null,
        bool $unreadOnly = false,
        ?string $module = null,
        ?string $shell = null,
    ): LengthAwarePaginator {
        $query = TenantNotification::query()
            ->where('user_id', $userId)
            ->whereIn('module', $modules)
            ->orderByDesc('created_at');

        if ($module !== null && in_array($module, $modules, true)) {
            $query->where('module', $module);
        }

        if ($shell !== null && in_array($shell, TenantNotificationShell::all(), true)) {
            TenantNotificationShell::apply($query, $shell);
        }

        if ($category === 'action' || $category === 'update') {
            $query->where('category', $category);
        }

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }
}
