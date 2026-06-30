<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementContractRegistryService
{
    public function __construct(
        private readonly ProcurementLifecycleAuditService $lifecycleAudit,
        private readonly ProcurementContractSpendService $spend,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $vendorId = null,
        ?string $siteId = null,
    ): LengthAwarePaginator {
        $query = ProcurementContract::query()
            ->with(['vendor:id,vendor_code,company_name', 'site:id,site_code,name', 'owner:id,name'])
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($vendorId !== null && $vendorId !== '') {
            $query->where('vendor_id', $vendorId);
        }

        if ($siteId !== null && $siteId !== '') {
            $query->where('site_id', $siteId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('vendor', static fn ($vendorQuery) => $vendorQuery
                        ->where('company_name', 'like', $like)
                        ->orWhere('vendor_code', 'like', $like));
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?ProcurementContract
    {
        return ProcurementContract::query()
            ->with([
                'vendor:id,vendor_code,company_name,accreditation_status',
                'site:id,site_code,name',
                'owner:id,name,email',
                'primaryDocument:id,title,expires_at,site_id',
                'documents',
            ])
            ->withCount('purchaseOrders')
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementContract $contract): array
    {
        return [
            'id' => (string) $contract->id,
            'document_no' => $contract->document_no,
            'status' => $contract->status,
            'status_label' => ProcurementContractStatus::label((string) $contract->status),
            'title' => $contract->title,
            'vendor' => $contract->vendor ? [
                'id' => (string) $contract->vendor->id,
                'vendor_code' => $contract->vendor->vendor_code,
                'company_name' => $contract->vendor->company_name,
            ] : null,
            'site' => $contract->site ? [
                'id' => (string) $contract->site->id,
                'site_code' => $contract->site->site_code,
                'name' => $contract->site->name,
            ] : null,
            'spend_ceiling' => $contract->spend_ceiling !== null ? (float) $contract->spend_ceiling : null,
            'committed_po_amount' => (float) $contract->committed_po_amount,
            'currency_code' => $contract->currency_code,
            'effective_from' => $contract->effective_from?->format('Y-m-d'),
            'end_date' => $contract->end_date?->format('Y-m-d'),
            'updated_at' => $contract->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementContract $contract): array
    {
        $committed = $this->spend->committedForContract($contract);
        $available = $this->spend->openCeiling($contract);

        return $this->toListPayload($contract) + [
            'description' => $contract->description,
            'owner' => $contract->owner ? [
                'id' => (string) $contract->owner->id,
                'name' => $contract->owner->name,
            ] : null,
            'primary_document_id' => $contract->primary_document_id,
            'primary_document' => $contract->primaryDocument ? [
                'id' => (string) $contract->primaryDocument->id,
                'title' => $contract->primaryDocument->title,
                'expires_at' => $contract->primaryDocument->expires_at?->toIso8601String(),
                'site_id' => $contract->primaryDocument->site_id,
            ] : null,
            'available_spend' => $available,
            'live_committed_po_amount' => $committed,
            'activated_at' => $contract->activated_at?->toIso8601String(),
            'terminated_at' => $contract->terminated_at?->toIso8601String(),
            'termination_reason' => $contract->termination_reason,
            'purchase_order_count' => (int) ($contract->purchase_orders_count ?? $contract->purchaseOrders()->count()),
            'documents' => $contract->documents->map(static fn ($doc) => [
                'id' => (string) $doc->id,
                'document_id' => $doc->document_id,
                'document_kind' => $doc->document_kind,
                'label' => $doc->label,
                'file_name' => $doc->file_name,
                'linked_at' => $doc->linked_at?->toIso8601String(),
            ])->values()->all(),
            'metadata' => $contract->metadata_json ?? [],
            'lifecycle_events' => $this->lifecycleAudit->listForDocument(
                ProcurementDocumentType::VENDOR_CONTRACT,
                (string) $contract->id,
            ),
            'binder_node_key' => 'vendor_contracts',
        ];
    }
}
