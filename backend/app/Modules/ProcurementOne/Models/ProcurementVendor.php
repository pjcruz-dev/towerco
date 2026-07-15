<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementVendor extends Model
{
    use HasUuids;

    protected $table = 'procurement_vendors';

    protected $fillable = [
        'master_data_row_id',
        'vendor_code',
        'company_name',
        'tax_id',
        'category',
        'schema_version',
        'contact_json',
        'banking_json',
        'address_json',
        'profile_json',
        'accreditation_status',
        'accredited_at',
        'accreditation_expires_at',
        'source_submission_id',
        'is_active',
        'portal_inbox_token_hash',
        'portal_inbox_token_encrypted',
        'portal_inbox_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'contact_json' => 'array',
            'banking_json' => 'array',
            'address_json' => 'array',
            'profile_json' => 'array',
            'accredited_at' => 'datetime',
            'accreditation_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'portal_inbox_opened_at' => 'datetime',
        ];
    }

    /** @return HasMany<ProcurementVendorAccreditationEvent, $this> */
    public function accreditationEvents(): HasMany
    {
        return $this->hasMany(ProcurementVendorAccreditationEvent::class, 'vendor_id')->orderByDesc('created_at');
    }

    /** @return HasMany<ProcurementVendorDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementVendorDocument::class, 'vendor_id')->orderByDesc('linked_at');
    }
}
