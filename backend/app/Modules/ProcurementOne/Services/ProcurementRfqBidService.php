<?php



declare(strict_types=1);



namespace App\Modules\ProcurementOne\Services;



use App\Modules\Identity\Models\TenantUser;

use App\Modules\ProcurementOne\Models\ProcurementRfq;

use App\Modules\ProcurementOne\Models\ProcurementRfqBid;

use App\Modules\ProcurementOne\Models\ProcurementRfqBidLine;

use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;

use App\Modules\ProcurementOne\Support\ProcurementDocumentType;

use App\Modules\ProcurementOne\Support\ProcurementRfqBidStatus;

use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;

use Illuminate\Http\UploadedFile;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;



final class ProcurementRfqBidService

{

    public function __construct(
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementRfqBidVersionService $versions,
        private readonly ProcurementRfqBidAttachmentService $attachments,
        private readonly ProcurementRfqBuyerNotificationService $buyerNotifications,
        private readonly ProcurementRfqBidLinePricingService $linePricing,
    ) {}



    /**

     * @param  array<string, mixed>  $input

     * @param  list<UploadedFile>  $attachmentFiles

     */

    public function captureFromPortal(

        ProcurementRfq $rfq,

        string $vendorId,

        array $input,

        ?string $contactName = null,

        array $attachmentFiles = [],

    ): ProcurementRfqBid {

        $input['vendor_id'] = $vendorId;



        return $this->capture($rfq, $input, null, 'portal', $contactName, $attachmentFiles);

    }



    /**

     * @param  array<string, mixed>  $input

     * @param  list<UploadedFile>  $attachmentFiles

     */

    public function capture(

        ProcurementRfq $rfq,

        array $input,

        ?TenantUser $actor = null,

        string $submittedVia = 'internal',

        ?string $portalContactName = null,

        array $attachmentFiles = [],

    ): ProcurementRfqBid {

        abort_unless(ProcurementRfqStatus::acceptsBids((string) $rfq->status), 422, __('This RFQ is not accepting quotations.'));



        $vendorId = (string) ($input['vendor_id'] ?? '');

        abort_if($vendorId === '', 422, __('Vendor is required.'));



        $invited = ProcurementRfqVendor::query()

            ->where('rfq_id', $rfq->id)

            ->where('vendor_id', $vendorId)

            ->exists();

        abort_unless($invited, 422, __('Vendor is not invited to this RFQ.'));



        $lines = $input['lines'] ?? [];

        if (! is_array($lines) || $lines === []) {

            throw ValidationException::withMessages([

                'lines' => [__('At least one bid line is required.')],

            ]);

        }



        $rfq->loadMissing('lines');

        $rfqLineIds = $rfq->lines->pluck('id')->map(static fn ($id) => (string) $id)->all();



        return DB::connection('tenant')->transaction(function () use ($rfq, $input, $actor, $vendorId, $lines, $rfqLineIds, $submittedVia, $portalContactName, $attachmentFiles): ProcurementRfqBid {

            $existing = ProcurementRfqBid::query()

                ->where('rfq_id', $rfq->id)

                ->where('vendor_id', $vendorId)

                ->lockForUpdate()

                ->first();



            $isRevision = $existing !== null && $existing->submitted_at !== null;



            if ($existing !== null && (string) $existing->status === ProcurementRfqBidStatus::AWARDED) {

                throw ValidationException::withMessages([

                    'bid' => [__('Awarded bids cannot be changed.')],

                ]);

            }



            $bid = $existing ?? new ProcurementRfqBid([

                'rfq_id' => (string) $rfq->id,

                'vendor_id' => $vendorId,

            ]);



            $leadTimes = [];
            $normalizedLines = [];
            $rfqLineMap = $this->linePricing->rfqLineMap($rfq->lines);

            foreach ($lines as $lineInput) {
                if (! is_array($lineInput)) {
                    continue;
                }

                $rfqLineId = (string) ($lineInput['rfq_line_id'] ?? '');
                if (! in_array($rfqLineId, $rfqLineIds, true)) {
                    throw ValidationException::withMessages([
                        'lines' => [__('Bid line references an invalid RFQ line.')],
                    ]);
                }

                $rfqLine = $rfqLineMap->get($rfqLineId);
                if ($rfqLine === null) {
                    throw ValidationException::withMessages([
                        'lines' => [__('Bid line references an invalid RFQ line.')],
                    ]);
                }

                $normalized = $this->linePricing->normalizeLine($lineInput, $rfqLine);

                if ($normalized['lead_time_days'] !== null) {
                    $leadTimes[] = (int) $normalized['lead_time_days'];
                }

                $normalizedLines[] = $normalized;
            }

            $summary = $this->linePricing->summarizeBid($normalizedLines);

            $bid->status = ProcurementRfqBidStatus::SUBMITTED;
            $bid->total_amount = $summary['total_amount'];
            $bid->total_amount_monthly = $summary['total_amount_monthly'];
            $bid->total_amount_yearly = $summary['total_amount_yearly'];
            $bid->normalized_annual_amount = $summary['normalized_annual_amount'];

            $bid->currency_code = (string) ($input['currency_code'] ?? $rfq->currency_code ?? 'PHP');

            $bid->validity_until = $input['validity_until'] ?? null;

            $bid->notes = $input['notes'] ?? null;

            $bid->captured_by_id = $actor !== null ? (string) $actor->id : null;

            $bid->submitted_at = now();

            $bid->avg_lead_time_days = $leadTimes !== [] ? (int) round(array_sum($leadTimes) / count($leadTimes)) : null;

            $bid->metadata_json = array_merge(is_array($bid->metadata_json) ? $bid->metadata_json : [], [

                'submitted_via' => $submittedVia,

                'portal_contact_name' => $portalContactName,

            ]);

            $bid->save();



            ProcurementRfqBidLine::query()->where('bid_id', $bid->id)->delete();

            foreach ($normalizedLines as $line) {
                ProcurementRfqBidLine::query()->create([
                    'bid_id' => (string) $bid->id,
                    'rfq_line_id' => $line['rfq_line_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'monthly_unit_price' => $line['monthly_unit_price'],
                    'yearly_unit_price' => $line['yearly_unit_price'],
                    'amount' => $line['amount'],
                    'amount_monthly' => $line['amount_monthly'],
                    'amount_yearly' => $line['amount_yearly'],
                    'normalized_annual_amount' => $line['normalized_annual_amount'],
                    'quote_basis' => $line['quote_basis'],
                    'lead_time_days' => $line['lead_time_days'],
                    'notes' => $line['notes'],
                ]);
            }



            $version = $this->versions->record($bid->refresh()->load('lines'), $submittedVia, $portalContactName, $actor);



            if ($attachmentFiles !== []) {

                $this->attachments->storeMany(

                    $bid,

                    $attachmentFiles,

                    $submittedVia,

                    $version,

                    $actor,

                );

            }



            ProcurementRfqVendor::query()

                ->where('rfq_id', $rfq->id)

                ->where('vendor_id', $vendorId)

                ->update([

                    'invitation_status' => 'submitted',

                    'responded_at' => now(),

                    'submitted_via' => $submittedVia,

                    'portal_contact_name' => $portalContactName,

                ]);



            $this->audit->record(

                ProcurementDocumentType::REQUEST_FOR_QUOTATION,

                (string) $rfq->id,

                $rfq->document_no,

                $isRevision

                    ? ($submittedVia === 'portal' ? 'portal_bid_revised' : 'bid_revised')

                    : ($submittedVia === 'portal' ? 'portal_bid_submitted' : 'bid_submitted'),

                $actor,

                null,

                [

                    'bid_id' => (string) $bid->id,

                    'vendor_id' => $vendorId,

                    'total_amount' => $bid->total_amount,

                    'version_no' => $version->version_no,

                ],

            );



            $bid = $bid->refresh()->load(['vendor', 'lines.rfqLine', 'versions', 'attachments']);

            $this->buyerNotifications->notifyBidCaptured($rfq, $bid, $isRevision, $actor);



            return $bid;

        });

    }

}


