<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\ProcurementOne\Notifications\ProcurementScheduledExportMailNotification;
use Illuminate\Support\Facades\Notification;

final class ProcurementScheduledExportMailService
{
    public function __construct(
        private readonly ProcurementExcelPackExportService $excelPack,
        private readonly ProcurementExportDateRangeService $dateRange,
        private readonly ProcurementExportScheduleService $schedule,
    ) {}

    /**
     * @return array{sent: int, period_label: string, filename: string}
     */
    public function sendScheduledExport(): array
    {
        $policy = $this->schedule->policy();
        $input = ['period' => $policy['period']];
        $range = $this->dateRange->resolve($input);
        $binary = $this->excelPack->buildBinary($input, $this->dateRange);
        $filename = $this->excelPack->filename($input, $this->dateRange);

        $sent = 0;
        foreach ($policy['recipients'] as $email) {
            SafeMailNotificationSender::sendNow(
                [Notification::route('mail', $email)],
                new ProcurementScheduledExportMailNotification(
                    $range['label'],
                    $filename,
                    $binary,
                ),
            );
            $sent++;
        }

        $this->schedule->markRunComplete();

        return [
            'sent' => $sent,
            'period_label' => $range['label'],
            'filename' => $filename,
        ];
    }
}
