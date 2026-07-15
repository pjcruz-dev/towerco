<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementVendorRegistryService
{
    private const SORTABLE = [
        'company_name',
        'vendor_code',
        'category',
        'accreditation_status',
        'updated_at',
    ];

    public function __construct(
        private readonly ProcurementVendorAccreditationPolicyService $policy,
        private readonly ProcurementPaymentRequestRegistryService $payments,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = ProcurementVendor::query()
            ->where('is_active', true);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('accreditation_status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('company_name', 'like', $like)
                    ->orWhere('tax_id', 'like', $like)
                    ->orWhere('vendor_code', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'company_name:asc'),
            self::SORTABLE,
            'company_name',
            'asc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByVendorCode(string $vendorCode): ?ProcurementVendor
    {
        $code = trim($vendorCode);
        if ($code === '') {
            return null;
        }

        return ProcurementVendor::query()
            ->where('is_active', true)
            ->where(static function ($q) use ($code): void {
                $q->where('vendor_code', $code)->orWhere('tax_id', $code);
            })
            ->first();
    }

    public function find(string $id): ?ProcurementVendor
    {
        return ProcurementVendor::query()
            ->with(['accreditationEvents', 'documents'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementVendor $vendor): array
    {
        return [
            'id' => (string) $vendor->id,
            'vendor_code' => $vendor->vendor_code,
            'company_name' => $vendor->company_name,
            'tax_id' => $vendor->tax_id,
            'category' => $vendor->category,
            'accreditation_status' => $vendor->accreditation_status,
            'accreditation_status_label' => ProcurementVendorAccreditationStatus::label((string) $vendor->accreditation_status),
            'accredited_at' => $vendor->accredited_at?->toIso8601String(),
            'accreditation_expires_at' => $vendor->accreditation_expires_at?->toIso8601String(),
            'is_active' => (bool) $vendor->is_active,
            'updated_at' => $vendor->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementVendor $vendor): array
    {
        return $this->toListPayload($vendor) + [
            'schema_version' => $vendor->schema_version,
            'master_data_row_id' => $vendor->master_data_row_id,
            'source_submission_id' => $vendor->source_submission_id,
            'contact' => $vendor->contact_json ?? [],
            'banking' => $vendor->banking_json ?? [],
            'address' => $vendor->address_json ?? [],
            'profile' => $vendor->profile_json ?? [],
            'accreditation_history' => $vendor->accreditationEvents
                ->map(static fn ($event) => [
                    'id' => (string) $event->id,
                    'status_from' => $event->status_from,
                    'status_to' => $event->status_to,
                    'reason' => $event->reason,
                    'actor_user_id' => $event->actor_user_id,
                    'submission_id' => $event->submission_id,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'documents' => $vendor->documents
                ->map(static fn ($doc) => [
                    'id' => (string) $doc->id,
                    'document_id' => $doc->document_id,
                    'e_approval_attachment_id' => $doc->e_approval_attachment_id,
                    'document_kind' => $doc->document_kind,
                    'label' => $doc->label,
                    'file_name' => $doc->file_name,
                    'linked_at' => $doc->linked_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'payment_history' => $vendor->vendor_code !== null
                ? $this->payments->paymentHistoryForVendor((string) $vendor->vendor_code)
                : [],
        ];
    }

    /**
     * Filter vendor master-data lookup options for PO forms.
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public function enrichVendorLookupOptions(array $options): array
    {
        $policy = $this->policy->policy();
        $block = $this->policy->blocksNonAccredited();

        $enriched = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $code = trim((string) ($option['code'] ?? $option['value'] ?? ''));
            $vendor = $code !== '' ? $this->findByVendorCode($code) : null;
            $status = $vendor?->accreditation_status ?? ProcurementVendorAccreditationStatus::PENDING;
            $statusLabel = ProcurementVendorAccreditationStatus::label((string) $status);

            if ($block && ! ProcurementVendorAccreditationStatus::isSelectableOnPo((string) $status)) {
                continue;
            }

            $subtitle = trim((string) ($option['subtitle'] ?? ''));
            if ($policy['enabled']) {
                $subtitle = $subtitle !== ''
                    ? "{$subtitle} · {$statusLabel}"
                    : $statusLabel;
            }

            $enriched[] = array_merge($option, [
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'accreditation_status' => $status,
                'accreditation_status_label' => $statusLabel,
                'procurement_vendor_id' => $vendor?->id,
            ]);
        }

        return $enriched;
    }
}
