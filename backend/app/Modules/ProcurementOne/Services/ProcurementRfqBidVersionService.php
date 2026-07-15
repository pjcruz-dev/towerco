<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidVersion;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidVersionLine;
use App\Modules\ProcurementOne\Support\ProcurementQuoteBasis;
use Illuminate\Support\Str;

final class ProcurementRfqBidVersionService
{
    public function record(
        ProcurementRfqBid $bid,
        string $submittedVia = 'internal',
        ?string $portalContactName = null,
        ?TenantUser $actor = null,
    ): ProcurementRfqBidVersion {
        $bid->loadMissing('lines');

        $nextVersion = ((int) ProcurementRfqBidVersion::query()
            ->where('bid_id', $bid->id)
            ->max('version_no')) + 1;

        $version = ProcurementRfqBidVersion::query()->create([
            'id' => (string) Str::uuid(),
            'bid_id' => (string) $bid->id,
            'version_no' => $nextVersion,
            'total_amount' => $bid->total_amount,
            'total_amount_monthly' => $bid->total_amount_monthly,
            'total_amount_yearly' => $bid->total_amount_yearly,
            'normalized_annual_amount' => $bid->normalized_annual_amount,
            'currency_code' => $bid->currency_code,
            'validity_until' => $bid->validity_until,
            'avg_lead_time_days' => $bid->avg_lead_time_days,
            'notes' => $bid->notes,
            'submitted_via' => $submittedVia,
            'captured_by_id' => $actor?->id,
            'portal_contact_name' => $portalContactName,
            'metadata_json' => is_array($bid->metadata_json) ? $bid->metadata_json : [],
            'recorded_at' => $bid->submitted_at ?? now(),
        ]);

        foreach ($bid->lines as $line) {
            ProcurementRfqBidVersionLine::query()->create([
                'id' => (string) Str::uuid(),
                'version_id' => (string) $version->id,
                'rfq_line_id' => (string) $line->rfq_line_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'monthly_unit_price' => $line->monthly_unit_price,
                'yearly_unit_price' => $line->yearly_unit_price,
                'amount' => $line->amount,
                'amount_monthly' => $line->amount_monthly,
                'amount_yearly' => $line->amount_yearly,
                'normalized_annual_amount' => $line->normalized_annual_amount,
                'quote_basis' => $line->quote_basis,
                'lead_time_days' => $line->lead_time_days,
                'notes' => $line->notes,
            ]);
        }

        return $version->load('lines.rfqLine');
    }

    /**
     * @return array<string, mixed>
     */
    public function versionPayload(ProcurementRfqBidVersion $version): array
    {
        $version->loadMissing(['lines.rfqLine', 'attachments', 'capturedBy:id,name']);

        return [
            'id' => (string) $version->id,
            'version_no' => (int) $version->version_no,
            'total_amount' => (float) $version->total_amount,
            'total_amount_monthly' => $version->total_amount_monthly !== null ? (float) $version->total_amount_monthly : null,
            'total_amount_yearly' => $version->total_amount_yearly !== null ? (float) $version->total_amount_yearly : null,
            'normalized_annual_amount' => $version->normalized_annual_amount !== null
                ? (float) $version->normalized_annual_amount
                : (float) $version->total_amount,
            'currency_code' => $version->currency_code,
            'validity_until' => $version->validity_until?->format('Y-m-d'),
            'avg_lead_time_days' => $version->avg_lead_time_days,
            'notes' => $version->notes,
            'submitted_via' => $version->submitted_via,
            'portal_contact_name' => $version->portal_contact_name,
            'captured_by' => $version->capturedBy ? [
                'id' => (string) $version->capturedBy->id,
                'name' => $version->capturedBy->name,
            ] : null,
            'recorded_at' => $version->recorded_at?->toIso8601String(),
            'lines' => $version->lines->map(static fn ($line) => [
                'rfq_line_id' => (string) $line->rfq_line_id,
                'description' => $line->rfqLine?->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'monthly_unit_price' => $line->monthly_unit_price !== null ? (float) $line->monthly_unit_price : null,
                'yearly_unit_price' => $line->yearly_unit_price !== null ? (float) $line->yearly_unit_price : null,
                'amount' => (float) $line->amount,
                'amount_monthly' => $line->amount_monthly !== null ? (float) $line->amount_monthly : null,
                'amount_yearly' => $line->amount_yearly !== null ? (float) $line->amount_yearly : null,
                'normalized_annual_amount' => $line->normalized_annual_amount !== null
                    ? (float) $line->normalized_annual_amount
                    : (float) $line->amount,
                'quote_basis' => $line->quote_basis,
                'quote_basis_label' => ProcurementQuoteBasis::label((string) ($line->quote_basis ?? ProcurementQuoteBasis::ONE_TIME)),
                'lead_time_days' => $line->lead_time_days,
                'notes' => $line->notes,
            ])->values()->all(),
            'attachments' => $version->attachments->map(static fn ($attachment) => [
                'id' => (string) $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ])->values()->all(),
        ];
    }
}
