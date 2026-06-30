<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPaymentBatch;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPaymentBatchStatus;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementPaymentBatchService
{
    public function __construct(
        private readonly ProcurementDocumentNumberAllocator $numbers,
        private readonly ProcurementLifecycleAuditService $audit,
    ) {}

    /**
     * @param  list<string>  $paymentRequestIds
     */
    public function createFromRequests(array $paymentRequestIds, array $input, TenantUser $actor): ProcurementPaymentBatch
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $paymentRequestIds))));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'payment_request_ids' => [__('Select at least one approved payment request.')],
            ]);
        }

        $scheduledDate = $input['scheduled_date'] ?? now()->addDays(3)->toDateString();
        $notes = $input['notes'] ?? null;

        return DB::connection('tenant')->transaction(function () use ($ids, $scheduledDate, $notes, $actor): ProcurementPaymentBatch {
            $requests = ProcurementPaymentRequest::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($requests->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'payment_request_ids' => [__('One or more payment requests were not found.')],
                ]);
            }

            foreach ($requests as $request) {
                if ((string) $request->status !== ProcurementPaymentRequestStatus::APPROVED) {
                    throw ValidationException::withMessages([
                        'payment_request_ids' => [__('All payment requests must be approved before batching.')],
                    ]);
                }
                if ($request->payment_batch_id !== null) {
                    throw ValidationException::withMessages([
                        'payment_request_ids' => [__('Payment request :no is already assigned to a batch.', ['no' => $request->document_no ?? $request->id])],
                    ]);
                }
            }

            $totalAmount = round((float) $requests->sum('amount'), 2);
            $currency = (string) ($requests->first()?->currency_code ?? 'PHP');

            $batch = ProcurementPaymentBatch::query()->create([
                'document_no' => $this->numbers->allocate(ProcurementDocumentType::PAYMENT_BATCH),
                'status' => ProcurementPaymentBatchStatus::SCHEDULED,
                'scheduled_date' => $scheduledDate,
                'total_amount' => $totalAmount,
                'currency_code' => $currency,
                'notes' => $notes,
                'created_by_id' => (string) $actor->id,
            ]);

            foreach ($requests as $request) {
                $request->payment_batch_id = (string) $batch->id;
                $request->status = ProcurementPaymentRequestStatus::SCHEDULED;
                $request->scheduled_date = $scheduledDate;
                $request->save();

                $this->audit->record(
                    ProcurementDocumentType::PAYMENT_REQUEST,
                    (string) $request->id,
                    $request->document_no,
                    'batched',
                    $actor,
                    null,
                    ['payment_batch_id' => (string) $batch->id, 'payment_batch_no' => $batch->document_no],
                );
            }

            $this->audit->record(
                ProcurementDocumentType::PAYMENT_BATCH,
                (string) $batch->id,
                $batch->document_no,
                'created',
                $actor,
                null,
                ['payment_request_count' => $requests->count(), 'total_amount' => $totalAmount],
            );

            return $batch->refresh()->load(['paymentRequests.apInvoice', 'createdBy:id,name']);
        });
    }

    public function markExported(ProcurementPaymentBatch $batch, TenantUser $actor): ProcurementPaymentBatch
    {
        abort_unless((string) $batch->status === ProcurementPaymentBatchStatus::SCHEDULED, 422, __('Only scheduled batches can be exported.'));

        return DB::connection('tenant')->transaction(function () use ($batch, $actor): ProcurementPaymentBatch {
            $batch->status = ProcurementPaymentBatchStatus::EXPORTED;
            $batch->exported_at = now();
            $batch->exported_by_id = (string) $actor->id;
            $batch->save();

            $this->audit->record(
                ProcurementDocumentType::PAYMENT_BATCH,
                (string) $batch->id,
                $batch->document_no,
                'exported',
                $actor,
            );

            return $batch->refresh()->load(['paymentRequests.apInvoice', 'createdBy:id,name']);
        });
    }

    public function markReconciled(ProcurementPaymentBatch $batch, TenantUser $actor): ProcurementPaymentBatch
    {
        abort_unless(in_array((string) $batch->status, [ProcurementPaymentBatchStatus::EXPORTED, ProcurementPaymentBatchStatus::SCHEDULED], true), 422, __('Only exported or scheduled batches can be reconciled.'));

        return DB::connection('tenant')->transaction(function () use ($batch, $actor): ProcurementPaymentBatch {
            $batch->status = ProcurementPaymentBatchStatus::RECONCILED;
            $batch->reconciled_at = now();
            $batch->reconciled_by_id = (string) $actor->id;
            $batch->save();

            $requests = ProcurementPaymentRequest::query()
                ->where('payment_batch_id', $batch->id)
                ->lockForUpdate()
                ->get();

            foreach ($requests as $request) {
                if ((string) $request->status === ProcurementPaymentRequestStatus::SCHEDULED) {
                    $request->status = ProcurementPaymentRequestStatus::PAID;
                    $request->paid_at = now();
                    $request->paid_by_id = (string) $actor->id;
                }
                if ((string) $request->status === ProcurementPaymentRequestStatus::PAID) {
                    $request->status = ProcurementPaymentRequestStatus::RECONCILED;
                    $request->reconciled_at = now();
                    $request->reconciled_by_id = (string) $actor->id;
                }
                $request->save();

                $this->audit->record(
                    ProcurementDocumentType::PAYMENT_REQUEST,
                    (string) $request->id,
                    $request->document_no,
                    'reconciled_via_batch',
                    $actor,
                    null,
                    ['payment_batch_id' => (string) $batch->id],
                );
            }

            $this->audit->record(
                ProcurementDocumentType::PAYMENT_BATCH,
                (string) $batch->id,
                $batch->document_no,
                'reconciled',
                $actor,
            );

            return $batch->refresh()->load(['paymentRequests.apInvoice', 'createdBy:id,name']);
        });
    }

    public function paginate(int $page, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        $query = ProcurementPaymentBatch::query()
            ->with(['createdBy:id,name'])
            ->withCount('paymentRequests')
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?ProcurementPaymentBatch
    {
        return ProcurementPaymentBatch::query()
            ->with(['paymentRequests.apInvoice', 'createdBy:id,name'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementPaymentBatch $batch): array
    {
        return [
            'id' => (string) $batch->id,
            'document_no' => $batch->document_no,
            'status' => $batch->status,
            'status_label' => ProcurementPaymentBatchStatus::label((string) $batch->status),
            'scheduled_date' => $batch->scheduled_date?->format('Y-m-d'),
            'total_amount' => (float) $batch->total_amount,
            'currency_code' => $batch->currency_code,
            'payment_request_count' => (int) ($batch->payment_requests_count ?? $batch->paymentRequests->count()),
            'exported_at' => $batch->exported_at?->toIso8601String(),
            'reconciled_at' => $batch->reconciled_at?->toIso8601String(),
            'created_by' => $batch->createdBy ? [
                'id' => (string) $batch->createdBy->id,
                'name' => $batch->createdBy->name,
            ] : null,
            'updated_at' => $batch->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementPaymentBatch $batch): array
    {
        $registry = app(ProcurementPaymentRequestRegistryService::class);

        return $this->toListPayload($batch) + [
            'notes' => $batch->notes,
            'payment_requests' => $batch->paymentRequests
                ->map(static fn (ProcurementPaymentRequest $request) => $registry->toListPayload($request))
                ->values()
                ->all(),
            'audit_trail' => $this->audit->listForDocument(
                ProcurementDocumentType::PAYMENT_BATCH,
                (string) $batch->id,
                50,
            ),
        ];
    }
}
