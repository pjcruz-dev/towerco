<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Models\ProcurementVendorAccreditationEvent;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProcurementVendorAccreditationService
{
    public function accreditFromRegistration(
        ProcurementVendor $vendor,
        EApprovalSubmission $submission,
        ?TenantUser $actor = null,
    ): ProcurementVendor {
        if ($vendor->accreditation_status === ProcurementVendorAccreditationStatus::ACCREDITED) {
            return $vendor;
        }

        return $this->transition(
            $vendor,
            ProcurementVendorAccreditationStatus::ACCREDITED,
            __('Vendor registration approved.'),
            $actor,
            (string) $submission->id,
            accreditedAt: now(),
        );
    }

    public function transition(
        ProcurementVendor $vendor,
        string $toStatus,
        ?string $reason = null,
        ?TenantUser $actor = null,
        ?string $submissionId = null,
        ?\DateTimeInterface $accreditedAt = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ProcurementVendor {
        if (! ProcurementVendorAccreditationStatus::isValid($toStatus)) {
            throw ValidationException::withMessages([
                'accreditation_status' => [__('Invalid accreditation status.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use (
            $vendor,
            $toStatus,
            $reason,
            $actor,
            $submissionId,
            $accreditedAt,
            $expiresAt,
        ): ProcurementVendor {
            $from = (string) $vendor->accreditation_status;

            if ($from === $toStatus) {
                return $vendor;
            }

            $vendor->accreditation_status = $toStatus;

            if ($toStatus === ProcurementVendorAccreditationStatus::ACCREDITED) {
                $vendor->accredited_at = $accreditedAt ?? now();
                $vendor->accreditation_expires_at = $expiresAt;
            }

            if (in_array($toStatus, [
                ProcurementVendorAccreditationStatus::SUSPENDED,
                ProcurementVendorAccreditationStatus::EXPIRED,
            ], true)) {
                $vendor->accreditation_expires_at = $expiresAt ?? now();
            }

            $vendor->save();

            ProcurementVendorAccreditationEvent::query()->create([
                'id' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'status_from' => $from,
                'status_to' => $toStatus,
                'reason' => $reason,
                'actor_user_id' => $actor?->id,
                'submission_id' => $submissionId,
                'created_at' => now(),
            ]);

            return $vendor->refresh();
        });
    }
}
