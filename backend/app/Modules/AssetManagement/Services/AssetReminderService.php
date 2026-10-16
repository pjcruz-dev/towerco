<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Models\AssetSetting;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use Illuminate\Support\Carbon;

final class AssetReminderService
{
    public function __construct(
        private readonly TenantNotificationService $notifications,
    ) {}

    /**
     * @return array{reminders: int, escalations: int}
     */
    public function run(): array
    {
        $days = $this->reminderDays();
        $escalateOn = max($days);
        $reminders = 0;
        $escalations = 0;
        $pending = Accountability::query()->where('status', 'PENDING_SIGNATURE')->whereNotNull('sent_at')->get();
        foreach ($pending as $record) {
            $sent = Carbon::parse($record->sent_at)->startOfDay();
            $age = $sent->diffInDays(now()->startOfDay());
            if (in_array($age, $days, true) && ($record->last_reminded_at === null || ! $record->last_reminded_at->isSameDay(now()))) {
                $this->notifications->notify(
                    userId: (string) $record->employee_id,
                    module: 'asset_management',
                    type: 'accountability_reminder',
                    message: __('Accountability :number is still unsigned.', ['number' => $record->number]),
                    subjectType: 'accountability',
                    subjectId: (string) $record->id,
                    href: '/assets/accountability/'.$record->id,
                    category: 'action',
                );
                $record->reminder_count = (int) $record->reminder_count + 1;
                $record->last_reminded_at = now();
                $record->save();
                $reminders++;
            }
            if ($age >= $escalateOn && $record->escalated_at === null) {
                $it = TenantUser::query()->permission('assets:manage')->get();
                foreach ($it as $user) {
                    $this->notifications->notify(
                        userId: (string) $user->id,
                        module: 'asset_management',
                        type: 'accountability_escalated',
                        message: __('Accountability :number has not been signed.', ['number' => $record->number]),
                        subjectType: 'accountability',
                        subjectId: (string) $record->id,
                        href: '/assets/accountability/'.$record->id,
                        category: 'action',
                    );
                }
                $record->escalated_at = now();
                $record->save();
                $escalations++;
            }
        }

        return ['reminders' => $reminders, 'escalations' => $escalations];
    }

    /**
     * @return list<int>
     */
    public function reminderDays(): array
    {
        $stored = AssetSetting::query()->find('accountability_reminder_days');
        $value = $stored?->value_json;
        if (! is_array($value) || $value === []) {
            return [1, 2, 3, 5];
        }
        $days = array_values(array_filter(array_map('intval', $value), static fn (int $day): bool => $day > 0));

        return $days === [] ? [1, 2, 3, 5] : $days;
    }
}
