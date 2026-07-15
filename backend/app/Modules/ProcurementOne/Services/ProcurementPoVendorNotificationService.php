<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Notifications\ProcurementPoVendorMailNotification;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class ProcurementPoVendorNotificationService
{
    public function __construct(
        private readonly ProcurementVendorEmailTemplateService $templates,
        private readonly ProcurementVendorRegistryService $vendors,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementPoPrintEnrichmentService $poAmounts,
    ) {}

    public function dispatchForEvent(ProcurementPo $po, string $event, TenantUser $actor, ?string $reason = null): bool
    {
        if (! $this->templates->isEnabledForEvent($event)) {
            return false;
        }

        $email = $this->resolveVendorEmail($po);
        if ($email === null) {
            return false;
        }

        $variables = $this->buildVariables($po, $reason);
        $subject = $this->templates->render($event, 'subject', $variables);
        $body = $this->templates->render($event, 'body', $variables);

        if ($subject === '' || $body === '') {
            return false;
        }

        $po = $po->loadMissing(['lines', 'requestor']);

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new ProcurementPoVendorMailNotification($po, $event, $subject, $body),
        );

        $this->audit->record(
            ProcurementDocumentType::PURCHASE_ORDER,
            (string) $po->id,
            $po->document_no,
            'vendor_email_'.$event,
            $actor,
            $reason,
            ['recipient' => $email],
        );

        return true;
    }

    public function maybeAutoDispatch(ProcurementPo $po, string $event, ?TenantUser $actor = null): void
    {
        $config = $this->templates->templates();
        $shouldSend = match ($event) {
            'po_approved' => $config['auto_on_approve'] && $config['po_approved']['enabled'],
            'po_sent' => $config['auto_on_sent'] && $config['po_sent']['enabled'],
            default => false,
        };

        if (! $shouldSend || $actor === null) {
            return;
        }

        $this->dispatchForEvent($po, $event, $actor);
    }

    public function sendManual(ProcurementPo $po, TenantUser $actor, string $event = 'po_sent'): bool
    {
        if (! in_array($event, ['po_approved', 'po_sent'], true)) {
            throw ValidationException::withMessages([
                'event' => [__('Only approved or sent vendor emails can be sent manually.')],
            ]);
        }

        if (! $this->templates->isEnabledForEvent($event)) {
            throw ValidationException::withMessages([
                'event' => [__('This vendor email template is disabled in procurement settings.')],
            ]);
        }

        return $this->dispatchForEvent($po, $event, $actor);
    }

    private function resolveVendorEmail(ProcurementPo $po): ?string
    {
        $vendor = null;
        if ($po->vendor_code !== null && trim((string) $po->vendor_code) !== '') {
            $vendor = $this->vendors->findByVendorCode((string) $po->vendor_code);
        }

        $contact = is_array($vendor?->contact_json) ? $vendor->contact_json : [];
        $candidates = [
            $contact['email'] ?? null,
            $contact['contact_email'] ?? null,
            $contact['primary_email'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $email = trim((string) $candidate);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(ProcurementPo $po, ?string $reason): array
    {
        $resolver = app(TenantAppUrlResolver::class);
        $printUrl = $po->e_approval_submission_id !== null
            ? $resolver->urlForCurrentTenant('/e-approval/submissions/'.$po->e_approval_submission_id.'/print')
            : $resolver->urlForCurrentTenant('/procurement/pos/'.$po->id);

        return [
            'document_no' => (string) ($po->document_no ?? $po->id),
            'supplier' => trim((string) ($po->supplier ?? $po->vendor_name ?? 'Vendor')),
            'grand_total' => number_format((float) $this->poAmounts->displayGrandTotal($po), 2).' '.($po->currency_code ?? 'PHP'),
            'print_url' => $printUrl,
            'reason' => trim((string) ($reason ?? '')),
            'brand' => $resolver->mailBrandLabel(),
        ];
    }
}
