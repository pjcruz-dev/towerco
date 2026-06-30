<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Notifications\ProcurementRfqVendorMailNotification;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use App\Modules\ProcurementOne\Support\ProcurementVendorContactResolver;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

final class ProcurementRfqVendorNotificationService
{
    public function __construct(
        private readonly ProcurementVendorEmailTemplateService $templates,
        private readonly ProcurementVendorContactResolver $contacts,
        private readonly ProcurementRfqVendorInvitationService $invitations,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly TenantAppUrlResolver $tenantUrls,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
        private readonly ProcurementVendorInboxTokenService $vendorInbox,
    ) {}

    public function maybeSendInvitation(
        ProcurementRfq $rfq,
        ProcurementRfqVendor $invitation,
        string $event,
        TenantUser $actor,
    ): bool {
        if (! $this->scoringPolicy->policy()['vendor_portal_enabled']) {
            return false;
        }

        if (! $this->shouldAutoSend($event)) {
            return false;
        }

        if ($event === 'rfq_invited' && (string) $rfq->status === ProcurementRfqStatus::DRAFT) {
            return false;
        }

        return $this->dispatch($rfq, $invitation, $event, $actor);
    }

    public function dispatch(
        ProcurementRfq $rfq,
        ProcurementRfqVendor $invitation,
        string $event,
        ?TenantUser $actor = null,
        bool $rotateToken = false,
        array $extraVariables = [],
    ): bool {
        if (! $this->templates->isEnabledForEvent($event)) {
            return false;
        }

        $invitation->loadMissing('vendor');
        $email = $this->contacts->resolveEmail($invitation->vendor);
        if ($email === null) {
            Log::info('RFQ vendor email skipped — no contact email.', [
                'rfq_id' => (string) $rfq->id,
                'vendor_id' => (string) $invitation->vendor_id,
                'event' => $event,
            ]);

            return false;
        }

        [$plainToken, $quoteUrl] = $this->invitations->issueToken($invitation, $rfq, $rotateToken);
        unset($plainToken);

        $variables = $this->buildVariables($rfq, $invitation, $quoteUrl, $extraVariables);
        $subject = $this->templates->render($event, 'subject', $variables);
        $body = $this->templates->render($event, 'body', $variables);

        if ($subject === '' || $body === '') {
            return false;
        }

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new ProcurementRfqVendorMailNotification(
                $subject,
                $body,
                $quoteUrl,
                __('Submit quotation'),
            ),
        );

        $invitation->invitation_email = $email;
        $invitation->invitation_sent_at = now();
        $invitation->save();

        $this->audit->record(
            ProcurementDocumentType::REQUEST_FOR_QUOTATION,
            (string) $rfq->id,
            $rfq->document_no,
            'vendor_email_'.$event,
            $actor,
            null,
            ['recipient' => $email, 'vendor_id' => (string) $invitation->vendor_id],
        );

        return true;
    }

    public function notifyAllInvited(ProcurementRfq $rfq, string $event, TenantUser $actor): int
    {
        $rfq->loadMissing('invitedVendors.vendor');
        $sent = 0;

        foreach ($rfq->invitedVendors as $invitation) {
            if ($this->maybeSendInvitation($rfq, $invitation, $event, $actor)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function dispatchReminder(ProcurementRfq $rfq, ProcurementRfqVendor $invitation, int $daysUntilClose): bool
    {
        if (! $this->scoringPolicy->policy()['vendor_portal_enabled']) {
            return false;
        }

        if (! $this->shouldAutoSend('rfq_reminder')) {
            return false;
        }

        if (! $this->templates->isEnabledForEvent('rfq_reminder')) {
            return false;
        }

        $invitation->loadMissing('vendor');
        $email = $this->contacts->resolveEmail($invitation->vendor);
        if ($email === null) {
            return false;
        }

        [$plainToken, $quoteUrl] = $this->invitations->issueToken($invitation, $rfq);
        unset($plainToken);

        $variables = $this->buildVariables($rfq, $invitation, $quoteUrl, [
            'days_until_close' => (string) $daysUntilClose,
        ]);
        $subject = $this->templates->render('rfq_reminder', 'subject', $variables);
        $body = $this->templates->render('rfq_reminder', 'body', $variables);

        if ($subject === '' || $body === '') {
            return false;
        }

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new ProcurementRfqVendorMailNotification(
                $subject,
                $body,
                $quoteUrl,
                __('Submit quotation'),
            ),
        );

        $invitation->invitation_email = $email;
        $invitation->invitation_sent_at = now();
        $invitation->save();

        $this->audit->record(
            ProcurementDocumentType::REQUEST_FOR_QUOTATION,
            (string) $rfq->id,
            $rfq->document_no,
            'vendor_email_rfq_reminder',
            null,
            null,
            ['recipient' => $email, 'vendor_id' => (string) $invitation->vendor_id, 'days_until_close' => $daysUntilClose],
        );

        return true;
    }

    public function notifyAllOnClose(ProcurementRfq $rfq, ?TenantUser $actor = null): int
    {
        if (! (bool) ($this->templates->templates()['auto_on_rfq_close'] ?? true)) {
            return 0;
        }

        if (! $this->templates->isEnabledForEvent('rfq_closed')) {
            return 0;
        }

        $rfq->loadMissing('invitedVendors.vendor');
        $sent = 0;

        foreach ($rfq->invitedVendors as $invitation) {
            if ($this->dispatch($rfq, $invitation, 'rfq_closed', $actor, false)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function buildVariables(ProcurementRfq $rfq, ProcurementRfqVendor $invitation, string $quoteUrl, array $extra = []): array
    {
        $vendorName = trim((string) ($invitation->vendor?->company_name ?? $invitation->vendor?->vendor_code ?? 'Vendor'));
        $closesAt = $rfq->bidding_closes_at?->timezone(config('app.timezone'))->format('Y-m-d H:i T') ?? __('See portal');
        $inboxUrl = '';
        if ($invitation->vendor !== null) {
            [, $inboxUrl] = $this->vendorInbox->ensureInboxUrl($invitation->vendor);
        }

        return array_merge([
            'document_no' => (string) ($rfq->document_no ?? $rfq->id),
            'rfq_document_no' => (string) ($rfq->document_no ?? $rfq->id),
            'rfq_title' => (string) $rfq->title,
            'vendor_name' => $vendorName,
            'supplier' => $vendorName,
            'closes_at' => $closesAt,
            'quote_url' => $quoteUrl,
            'inbox_url' => $inboxUrl,
            'brand' => $this->tenantUrls->mailBrandLabel(),
        ], $extra);
    }

    private function shouldAutoSend(string $event): bool
    {
        $templates = $this->templates->templates();

        return match ($event) {
            'rfq_invited' => (bool) ($templates['auto_on_rfq_invite'] ?? true),
            'rfq_published' => (bool) ($templates['auto_on_rfq_publish'] ?? true),
            'rfq_reminder' => (bool) ($templates['auto_on_rfq_reminder'] ?? true),
            'rfq_closed' => (bool) ($templates['auto_on_rfq_close'] ?? true),
            default => false,
        };
    }
}
