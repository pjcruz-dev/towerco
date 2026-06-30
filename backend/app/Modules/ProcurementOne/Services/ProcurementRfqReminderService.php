<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use Illuminate\Support\Carbon;

final class ProcurementRfqReminderService
{
    public function __construct(
        private readonly ProcurementRfqVendorNotificationService $vendorNotifications,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
    ) {}

    /**
     * @return array{reminders_sent: int, rfqs_scanned: int}
     */
    public function run(): array
    {
        if (! $this->scoringPolicy->policy()['vendor_portal_enabled']) {
            return ['reminders_sent' => 0, 'rfqs_scanned' => 0];
        }

        $thresholds = $this->reminderDays();
        if ($thresholds === []) {
            return ['reminders_sent' => 0, 'rfqs_scanned' => 0];
        }

        $rfqs = ProcurementRfq::query()
            ->where('status', ProcurementRfqStatus::OPEN)
            ->whereNotNull('bidding_closes_at')
            ->with(['invitedVendors.vendor'])
            ->get();

        $sent = 0;

        foreach ($rfqs as $rfq) {
            $closesAt = $rfq->bidding_closes_at;
            if (! $closesAt instanceof Carbon) {
                continue;
            }

            $daysUntilClose = (int) now()->startOfDay()->diffInDays($closesAt->copy()->startOfDay(), false);
            if (! in_array($daysUntilClose, $thresholds, true)) {
                continue;
            }

            foreach ($rfq->invitedVendors as $invitation) {
                if ($this->vendorAlreadySubmitted($invitation)) {
                    continue;
                }

                if ($this->reminderAlreadySent($invitation, $daysUntilClose)) {
                    continue;
                }

                if ($this->vendorNotifications->dispatchReminder($rfq, $invitation, $daysUntilClose)) {
                    $this->markReminderSent($invitation, $daysUntilClose);
                    $sent++;
                }
            }
        }

        return ['reminders_sent' => $sent, 'rfqs_scanned' => $rfqs->count()];
    }

    /**
     * @return list<int>
     */
    private function reminderDays(): array
    {
        $configured = config('procurement_one.rfq_reminders.days_before_close', [3, 1]);
        if (! is_array($configured)) {
            return [3, 1];
        }

        return array_values(array_unique(array_map(
            static fn ($day) => max(0, (int) $day),
            $configured,
        )));
    }

    private function vendorAlreadySubmitted(ProcurementRfqVendor $invitation): bool
    {
        return in_array((string) $invitation->invitation_status, ['submitted', 'awarded'], true);
    }

    private function reminderAlreadySent(ProcurementRfqVendor $invitation, int $daysUntilClose): bool
    {
        $log = is_array($invitation->reminder_log_json) ? $invitation->reminder_log_json : [];

        return in_array($daysUntilClose, $log, true);
    }

    private function markReminderSent(ProcurementRfqVendor $invitation, int $daysUntilClose): void
    {
        $log = is_array($invitation->reminder_log_json) ? $invitation->reminder_log_json : [];
        $log[] = $daysUntilClose;
        $invitation->reminder_log_json = array_values(array_unique($log));
        $invitation->save();
    }
}
