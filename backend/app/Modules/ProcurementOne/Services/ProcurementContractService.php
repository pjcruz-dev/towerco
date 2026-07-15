<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Models\ProcurementContractDocument;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementContractService
{
    public function __construct(
        private readonly ProcurementDocumentNumberAllocator $numbers,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementContractDocumentSyncService $documentSync,
        private readonly ProcurementContractRegistryService $registry,
    ) {}

    public function find(string $id): ?ProcurementContract
    {
        return $this->registry->find($id);
    }

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $vendorId = null,
        ?string $siteId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        return $this->registry->paginate($page, $perPage, $search, $status, $vendorId, $siteId, $sort);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, TenantUser $actor): ProcurementContract
    {
        $vendor = ProcurementVendor::query()->find((string) $input['vendor_id']);
        abort_if($vendor === null, 422, __('Vendor is required for a contract.'));

        return DB::connection('tenant')->transaction(function () use ($input, $actor, $vendor): ProcurementContract {
            $contract = ProcurementContract::query()->create([
                'status' => ProcurementContractStatus::DRAFT,
                'title' => trim((string) $input['title']),
                'description' => $input['description'] ?? null,
                'vendor_id' => (string) $vendor->id,
                'site_id' => $input['site_id'] ?? null,
                'primary_document_id' => $input['primary_document_id'] ?? null,
                'spend_ceiling' => isset($input['spend_ceiling']) ? (float) $input['spend_ceiling'] : null,
                'currency_code' => (string) ($input['currency_code'] ?? 'PHP'),
                'effective_from' => $input['effective_from'] ?? null,
                'end_date' => $input['end_date'] ?? null,
                'owner_id' => (string) $actor->id,
                'metadata_json' => [
                    'binder_node_key' => 'vendor_contracts',
                ],
            ]);

            $this->syncLinkedDocuments($contract, $input['documents'] ?? []);
            $this->documentSync->syncPrimaryDocumentExpiry($contract->refresh());
            $this->refreshStatusIfExpired($contract);

            $this->audit->record(
                ProcurementDocumentType::VENDOR_CONTRACT,
                (string) $contract->id,
                null,
                'created',
                $actor,
            );

            return $this->registry->find((string) $contract->id) ?? $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(ProcurementContract $contract, array $input, TenantUser $actor): ProcurementContract
    {
        if (! ProcurementContractStatus::isEditable((string) $contract->status)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft contracts can be edited.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($contract, $input, $actor): ProcurementContract {
            $contract->fill(array_filter([
                'title' => array_key_exists('title', $input) ? trim((string) $input['title']) : null,
                'description' => $input['description'] ?? null,
                'vendor_id' => $input['vendor_id'] ?? null,
                'site_id' => $input['site_id'] ?? null,
                'primary_document_id' => $input['primary_document_id'] ?? null,
                'spend_ceiling' => array_key_exists('spend_ceiling', $input)
                    ? ($input['spend_ceiling'] === null ? null : (float) $input['spend_ceiling'])
                    : null,
                'currency_code' => $input['currency_code'] ?? null,
                'effective_from' => $input['effective_from'] ?? null,
                'end_date' => $input['end_date'] ?? null,
            ], static fn ($value) => $value !== null));

            $contract->save();

            if (array_key_exists('documents', $input) && is_array($input['documents'])) {
                $this->syncLinkedDocuments($contract, $input['documents']);
            }

            $this->documentSync->syncPrimaryDocumentExpiry($contract->refresh());
            $this->refreshStatusIfExpired($contract);

            $this->audit->record(
                ProcurementDocumentType::VENDOR_CONTRACT,
                (string) $contract->id,
                $contract->document_no,
                'updated',
                $actor,
            );

            return $this->registry->find((string) $contract->id) ?? $contract;
        });
    }

    public function activate(ProcurementContract $contract, TenantUser $actor): ProcurementContract
    {
        abort_unless((string) $contract->status === ProcurementContractStatus::DRAFT, 422, __('Only draft contracts can be activated.'));

        return DB::connection('tenant')->transaction(function () use ($contract, $actor): ProcurementContract {
            if ($contract->document_no === null) {
                $contract->document_no = $this->numbers->allocate(ProcurementDocumentType::VENDOR_CONTRACT);
            }

            $contract->status = ProcurementContractStatus::ACTIVE;
            $contract->activated_at = now();
            $contract->save();

            $this->documentSync->syncPrimaryDocumentExpiry($contract);
            $this->refreshStatusIfExpired($contract);

            $this->audit->record(
                ProcurementDocumentType::VENDOR_CONTRACT,
                (string) $contract->id,
                $contract->document_no,
                'activated',
                $actor,
            );

            return $this->registry->find((string) $contract->id) ?? $contract;
        });
    }

    public function terminate(ProcurementContract $contract, TenantUser $actor, string $reason): ProcurementContract
    {
        abort_unless((string) $contract->status === ProcurementContractStatus::ACTIVE, 422, __('Only active contracts can be terminated.'));

        return DB::connection('tenant')->transaction(function () use ($contract, $actor, $reason): ProcurementContract {
            $contract->status = ProcurementContractStatus::TERMINATED;
            $contract->terminated_at = now();
            $contract->terminated_by_id = (string) $actor->id;
            $contract->termination_reason = trim($reason);
            $contract->save();

            $this->audit->record(
                ProcurementDocumentType::VENDOR_CONTRACT,
                (string) $contract->id,
                $contract->document_no,
                'terminated',
                $actor,
                $reason,
            );

            return $this->registry->find((string) $contract->id) ?? $contract;
        });
    }

    public function refreshStatusIfExpired(ProcurementContract $contract): void
    {
        if ($contract->end_date === null || (string) $contract->status !== ProcurementContractStatus::ACTIVE) {
            return;
        }

        if ($contract->end_date->lt(Carbon::today())) {
            $contract->status = ProcurementContractStatus::EXPIRED;
            $contract->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private function syncLinkedDocuments(ProcurementContract $contract, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            ProcurementContractDocument::query()->create([
                'contract_id' => (string) $contract->id,
                'document_id' => $document['document_id'] ?? null,
                'document_kind' => (string) ($document['document_kind'] ?? 'ancillary'),
                'label' => trim((string) ($document['label'] ?? 'Attachment')),
                'file_name' => $document['file_name'] ?? null,
                'linked_at' => now(),
            ]);
        }
    }
}
