<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Notifications\ProcurementRfqBuyerMailNotification;
use App\Modules\ProcurementOne\Support\ProcurementNotificationCategory;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Notification;

final class ProcurementRfqBuyerNotificationService
{
    public function __construct(
        private readonly TenantNotificationService $tenantNotifications,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
        private readonly TenantAppUrlResolver $tenantUrls,
    ) {}

    public function notifyBidCaptured(
        ProcurementRfq $rfq,
        ProcurementRfqBid $bid,
        bool $isRevision,
        ?TenantUser $actor = null,
    ): void {
        $policy = $this->scoringPolicy->policy();
        if (! (bool) ($policy['notify_buyer_on_bid'] ?? true)) {
            return;
        }

        $rfq->loadMissing(['requestor:id,name,email']);
        $bid->loadMissing('vendor');

        $requestor = $rfq->requestor;
        if ($requestor === null) {
            return;
        }

        $vendorName = trim((string) ($bid->vendor?->company_name ?? $bid->vendor?->vendor_code ?? 'Vendor'));
        $documentNo = (string) ($rfq->document_no ?? $rfq->id);
        $type = $isRevision ? 'rfq_bid_revised' : 'rfq_bid_received';
        $message = $isRevision
            ? __(':vendor revised their quotation on RFQ :number.', ['vendor' => $vendorName, 'number' => $documentNo])
            : __(':vendor submitted a quotation on RFQ :number.', ['vendor' => $vendorName, 'number' => $documentNo]);

        $bodyPreview = __('Total: :amount :currency', [
            'amount' => number_format((float) $bid->total_amount, 2),
            'currency' => (string) $bid->currency_code,
        ]);

        $this->tenantNotifications->notify(
            userId: (string) $requestor->id,
            module: TenantNotificationModule::PROCUREMENT_ONE,
            type: $type,
            message: $message,
            subjectType: 'rfq',
            subjectId: (string) $rfq->id,
            contextPrimary: $documentNo,
            contextSecondary: $rfq->title,
            bodyPreview: $bodyPreview,
            href: ProcurementNotificationCategory::hrefFor((string) $rfq->id),
            actor: $actor,
        );

        if (! (bool) ($policy['notify_buyer_email'] ?? true)) {
            return;
        }

        $email = trim((string) ($requestor->email ?? ''));
        if ($email === '') {
            return;
        }

        $rfqUrl = $this->tenantUrls->urlForCurrentTenant(ProcurementNotificationCategory::hrefFor((string) $rfq->id));
        $subject = $isRevision
            ? __('Quotation revised — RFQ :number', ['number' => $documentNo])
            : __('New quotation — RFQ :number', ['number' => $documentNo]);
        $body = $isRevision
            ? __(":vendor updated their quotation for :title.\n\nTotal: :amount :currency\n\nReview: :url", [
                'vendor' => $vendorName,
                'title' => (string) $rfq->title,
                'amount' => number_format((float) $bid->total_amount, 2),
                'currency' => (string) $bid->currency_code,
                'url' => $rfqUrl,
            ])
            : __(":vendor submitted a quotation for :title.\n\nTotal: :amount :currency\n\nReview: :url", [
                'vendor' => $vendorName,
                'title' => (string) $rfq->title,
                'amount' => number_format((float) $bid->total_amount, 2),
                'currency' => (string) $bid->currency_code,
                'url' => $rfqUrl,
            ]);

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new ProcurementRfqBuyerMailNotification(
                $subject,
                $body,
                $rfqUrl,
                __('View RFQ'),
            ),
        );
    }

    public function notifyBiddingClosed(ProcurementRfq $rfq, bool $autoClosed = false): void
    {
        if (! (bool) ($this->scoringPolicy->policy()['notify_buyer_on_bid'] ?? true)) {
            return;
        }

        $rfq->loadMissing(['requestor:id,name,email']);
        $requestor = $rfq->requestor;
        if ($requestor === null) {
            return;
        }

        $documentNo = (string) ($rfq->document_no ?? $rfq->id);
        $message = $autoClosed
            ? __('Bidding closed automatically for RFQ :number — review quotations and award.', ['number' => $documentNo])
            : __('Bidding closed for RFQ :number — review quotations and award.', ['number' => $documentNo]);

        $this->tenantNotifications->notify(
            userId: (string) $requestor->id,
            module: TenantNotificationModule::PROCUREMENT_ONE,
            type: 'rfq_bidding_closed',
            message: $message,
            subjectType: 'rfq',
            subjectId: (string) $rfq->id,
            contextPrimary: $documentNo,
            contextSecondary: $rfq->title,
            bodyPreview: $autoClosed ? __('Deadline reached') : __('Ready for award'),
            href: ProcurementNotificationCategory::hrefFor((string) $rfq->id),
        );

        if (! (bool) ($this->scoringPolicy->policy()['notify_buyer_email'] ?? true)) {
            return;
        }

        $email = trim((string) ($requestor->email ?? ''));
        if ($email === '') {
            return;
        }

        $rfqUrl = $this->tenantUrls->urlForCurrentTenant(ProcurementNotificationCategory::hrefFor((string) $rfq->id));
        $subject = $autoClosed
            ? __('Bidding closed — RFQ :number', ['number' => $documentNo])
            : __('Bidding closed — RFQ :number', ['number' => $documentNo]);
        $body = $autoClosed
            ? __("Bidding has closed automatically for :title.\n\nReview vendor quotations and proceed to award.\n\nOpen RFQ: :url", [
                'title' => (string) $rfq->title,
                'url' => $rfqUrl,
            ])
            : __("Bidding has closed for :title.\n\nReview vendor quotations and proceed to award.\n\nOpen RFQ: :url", [
                'title' => (string) $rfq->title,
                'url' => $rfqUrl,
            ]);

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $email)],
            new ProcurementRfqBuyerMailNotification(
                $subject,
                $body,
                $rfqUrl,
                __('View RFQ'),
            ),
        );
    }
}
