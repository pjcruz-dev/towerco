<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentReviewAlert;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Documents\Support\DocumentsNotificationCategory;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationModule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * In-app alerts when a published controlled document's next review date is inside 90, 60, or 30 days.
 */
final class ControlledDocumentReviewNotificationService
{
    /** @var list<int> */
    private const WINDOWS = [90, 60, 30];

    public function __construct(
        private readonly TenantNotificationService $notifications,
    ) {}

    /**
     * @return array{alerts_sent: int, documents_scanned: int}
     */
    public function run(): array
    {
        if (! Schema::connection('tenant')->hasTable('controlled_documents')
            || ! Schema::connection('tenant')->hasTable('controlled_document_review_alerts')) {
            return ['alerts_sent' => 0, 'documents_scanned' => 0];
        }

        $today = Carbon::today();
        $horizon = $today->copy()->addDays(90);
        $alertsSent = 0;
        $documentsScanned = 0;

        $documents = ControlledDocument::query()
            ->where('status', ControlledDocumentStatus::PUBLISHED)
            ->whereNotNull('next_review_date')
            ->whereDate('next_review_date', '>=', $today->toDateString())
            ->whereDate('next_review_date', '<=', $horizon->toDateString())
            ->get();

        $recipients = TenantUser::permission('documents:controlled:view')->get();
        if ($recipients->isEmpty()) {
            return ['alerts_sent' => 0, 'documents_scanned' => $documents->count()];
        }

        foreach ($documents as $document) {
            $documentsScanned++;
            $reviewAt = $document->next_review_date;
            if ($reviewAt === null) {
                continue;
            }

            $daysUntil = (int) $today->diffInDays($reviewAt->copy()->startOfDay(), false);
            if ($daysUntil < 0) {
                continue;
            }

            foreach (self::WINDOWS as $window) {
                if (! $this->shouldAlertForWindow($daysUntil, $window)) {
                    continue;
                }

                if ($this->alreadySent($document, $window)) {
                    continue;
                }

                $this->sendAlerts($document, $window, $daysUntil, $recipients);
                ControlledDocumentReviewAlert::query()->create([
                    'id' => (string) Str::uuid(),
                    'controlled_document_id' => $document->id,
                    'window_days' => $window,
                    'sent_at' => now(),
                ]);
                $alertsSent++;
            }
        }

        return [
            'alerts_sent' => $alertsSent,
            'documents_scanned' => $documentsScanned,
        ];
    }

    private function shouldAlertForWindow(int $daysUntil, int $window): bool
    {
        if ($window === 90) {
            return $daysUntil <= 90 && $daysUntil > 60;
        }

        if ($window === 60) {
            return $daysUntil <= 60 && $daysUntil > 30;
        }

        return $daysUntil <= 30;
    }

    private function alreadySent(ControlledDocument $document, int $window): bool
    {
        return ControlledDocumentReviewAlert::query()
            ->where('controlled_document_id', $document->id)
            ->where('window_days', $window)
            ->exists();
    }

    /**
     * @param  Collection<int, TenantUser>  $recipients
     */
    private function sendAlerts(
        ControlledDocument $document,
        int $window,
        int $daysUntil,
        $recipients,
    ): void {
        $code = $document->document_code !== null && $document->document_code !== ''
            ? (string) $document->document_code
            : 'Document';
        $message = __('Controlled document review in :days days: :title (:code).', [
            'days' => $daysUntil,
            'title' => $document->title,
            'code' => $code,
        ]);

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                userId: (string) $recipient->id,
                module: TenantNotificationModule::DOCUMENTS,
                type: 'controlled_document_review',
                message: $message,
                subjectType: 'controlled_document',
                subjectId: (string) $document->id,
                contextPrimary: (string) $document->title,
                contextSecondary: $code.' · '.$window.'d',
                bodyPreview: $message,
                href: '/documents/controlled',
                category: DocumentsNotificationCategory::forType('controlled_document_review'),
            );
        }
    }
}
