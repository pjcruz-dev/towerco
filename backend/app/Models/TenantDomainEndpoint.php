<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantDomainEndpoint extends Model
{
    use CentralConnection;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purpose',
        'hostname',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            app(SanctumStatefulDomainResolver::class)->forget();
            app(CorsAllowedOriginResolver::class)->forget();
        });

        static::deleted(static function (): void {
            app(SanctumStatefulDomainResolver::class)->forget();
            app(CorsAllowedOriginResolver::class)->forget();
        });
    }
}
