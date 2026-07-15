<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentExpiryAlert;
use App\Modules\Documents\Support\DocumentsNotificationCategory;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationModule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DocumentExpiryNotificationService
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
        $today = Carbon::today();
        $alertsSent = 0;
        $documentsScanned = 0;

        $documents = Document::query()
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $today)
            ->whereIn('status', [DocumentStatus::DRAFT, DocumentStatus::FINAL])
            ->with(['site:id,site_code,name'])
            ->get();

        $recipients = TenantUser::permission('documents:view')->get();
        if ($recipients->isEmpty()) {
            return ['alerts_sent' => 0, 'documents_scanned' => $documents->count()];
        }

        foreach ($documents as $document) {
            $documentsScanned++;
            $expiresAt = $document->expires_at;
            if ($expiresAt === null) {
                continue;
            }

            $daysUntil = (int) $today->diffInDays($expiresAt->copy()->startOfDay(), false);
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
                DocumentExpiryAlert::query()->create([
                    'id' => (string) Str::uuid(),
                    'document_id' => $document->id,
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

    private function alreadySent(Document $document, int $window): bool
    {
        return DocumentExpiryAlert::query()
            ->where('document_id', $document->id)
            ->where('window_days', $window)
            ->exists();
    }

    /**
     * @param  Collection<int, TenantUser>  $recipients
     */
    private function sendAlerts(
        Document $document,
        int $window,
        int $daysUntil,
        $recipients,
    ): void {
        $siteCode = $document->site?->site_code ?? __('Site');
        $message = __('Document expires in :days days: :title (:site).', [
            'days' => $daysUntil,
            'title' => $document->title,
            'site' => $siteCode,
        ]);

        $href = DocumentsNotificationCategory::hrefFor(
            (string) $document->id,
            $document->site_id !== null ? (string) $document->site_id : null,
        );

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                userId: (string) $recipient->id,
                module: TenantNotificationModule::DOCUMENTS,
                type: 'document_expiring',
                message: $message,
                subjectType: 'document',
                subjectId: (string) $document->id,
                contextPrimary: $document->title,
                contextSecondary: $siteCode.' · '.$window.'d',
                bodyPreview: $message,
                href: $href,
                category: DocumentsNotificationCategory::forType('document_expiring'),
            );
        }
    }
}
