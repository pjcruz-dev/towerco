<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Support\TenantNotificationCategoryResolver;
use Illuminate\Support\Str;

final class TenantNotificationService
{
    public function __construct(
        private readonly TenantNotificationBroadcaster $broadcaster,
    ) {}

    public function notify(
        string $userId,
        string $module,
        string $type,
        string $message,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $contextPrimary = null,
        ?string $contextSecondary = null,
        ?string $bodyPreview = null,
        ?string $href = null,
        ?TenantUser $actor = null,
        ?string $category = null,
    ): void {
        $notification = TenantNotification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'module' => $module,
            'type' => $type,
            'category' => $category ?? TenantNotificationCategoryResolver::for($module, $type),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context_primary' => $contextPrimary,
            'context_secondary' => $contextSecondary,
            'message' => $message,
            'body_preview' => $bodyPreview !== null ? $this->trimPreview($bodyPreview) : null,
            'href' => $href ?? TenantNotificationCategoryResolver::hrefFor(
                $module,
                $type,
                $subjectType,
                $subjectId,
            ),
            'is_read' => false,
        ]);

        $this->broadcaster->created($notification);
    }

    /**
     * @param  list<string>  $modules
     */
    public function unreadCount(string $userId, array $modules): int
    {
        if ($modules === []) {
            return 0;
        }

        return TenantNotification::query()
            ->where('user_id', $userId)
            ->whereIn('module', $modules)
            ->where('is_read', false)
            ->count();
    }

    public function markRead(string $userId, string $notificationId, ?string $module = null): void
    {
        $query = TenantNotification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId);

        if ($module !== null) {
            $query->where('module', $module);
        }

        $query->update(['is_read' => true]);
    }

    /**
     * @param  list<string>  $modules
     */
    public function markAllRead(string $userId, array $modules, ?string $category = null, ?string $module = null): void
    {
        if ($modules === []) {
            return;
        }

        $query = TenantNotification::query()
            ->where('user_id', $userId)
            ->whereIn('module', $modules)
            ->where('is_read', false);

        if ($module !== null && in_array($module, $modules, true)) {
            $query->where('module', $module);
        }

        if ($category === 'action' || $category === 'update') {
            $query->where('category', $category);
        }

        $query->update(['is_read' => true]);
    }

    private function trimPreview(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= 160) {
            return $text;
        }

        return mb_substr($text, 0, 157).'…';
    }
}
