<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'it_assets';

    protected $fillable = [
        'asset_tag',
        'serial_number',
        'asset_type',
        'status',
        'manufacturer',
        'product_name',
        'product_version',
        'hostname',
        'old_hostname',
        'processor',
        'installed_ram',
        'operating_system',
        'system_type',
        'bios_version',
        'purchase_date',
        'warranty_end',
        'commission_date',
        'assigned_user_id',
        'custom_values_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_end' => 'date',
            'commission_date' => 'date',
            'custom_values_json' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'assigned_user_id');
    }

    /** @return HasMany<AssetAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    /** @return HasMany<AssetEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class, 'asset_id');
    }

    /** @return HasMany<AssetNetworkAdapter, $this> */
    public function networkAdapters(): HasMany
    {
        return $this->hasMany(AssetNetworkAdapter::class, 'asset_id');
    }

    /** @return HasMany<AssetDisk, $this> */
    public function disks(): HasMany
    {
        return $this->hasMany(AssetDisk::class, 'asset_id');
    }

    /** @return HasMany<AssetVideoCard, $this> */
    public function videoCards(): HasMany
    {
        return $this->hasMany(AssetVideoCard::class, 'asset_id');
    }

    /** @return HasMany<AssetApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(AssetApplication::class, 'asset_id');
    }

    /** @return HasMany<AssetRepair, $this> */
    public function repairs(): HasMany
    {
        return $this->hasMany(AssetRepair::class, 'asset_id');
    }

    /** @return HasMany<HostnameHistory, $this> */
    public function hostnameHistory(): HasMany
    {
        return $this->hasMany(HostnameHistory::class, 'asset_id');
    }
}
