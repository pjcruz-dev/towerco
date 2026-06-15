<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Validation\ValidationException;

final class TicketingPlanFeaturesService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array{plan_tier: string, enabled: bool, file_uploads: bool, max_attachments_per_ticket: int|null}
     */
    public function snapshot(?string $tenantId = null): array
    {
        return $this->entitlements->ticketingFeatures($tenantId);
    }

    public function moduleEnabled(): bool
    {
        return $this->snapshot()['enabled'];
    }

    public function fileUploadsAllowed(): bool
    {
        return $this->snapshot()['file_uploads'];
    }

    public function maxAttachmentsPerTicket(): ?int
    {
        $max = $this->snapshot()['max_attachments_per_ticket'];

        return $max;
    }

    public function assertModuleEnabled(): void
    {
        if (! $this->moduleEnabled()) {
            throw ValidationException::withMessages([
                'ticketing' => [__('Ticketing is not included on your current plan.')],
            ]);
        }
    }

    public function assertCanUploadAttachment(): void
    {
        $this->assertModuleEnabled();

        if (! $this->fileUploadsAllowed()) {
            throw ValidationException::withMessages([
                'file' => [__('File uploads are not included on your current plan.')],
            ]);
        }
    }

    public function assertAttachmentLimitNotExceeded(int $currentCount): void
    {
        $max = $this->maxAttachmentsPerTicket();
        if ($max === null) {
            return;
        }

        if ($currentCount >= $max) {
            throw ValidationException::withMessages([
                'file' => [__('Your plan allows at most :max attachment(s) per ticket.', ['max' => $max])],
            ]);
        }
    }
}
