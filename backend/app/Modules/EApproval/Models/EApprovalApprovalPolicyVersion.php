<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalApprovalPolicyVersion extends Model
{
    use HasUuids;

    protected $table = 'e_approval_approval_policy_versions';

    protected $fillable = [
        'policy_id',
        'version_number',
        'status',
        'config_json',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EApprovalApprovalPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(EApprovalApprovalPolicy::class, 'policy_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'published_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        try {
            $decoded = json_decode($this->config_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function label(): string
    {
        return __('Approval policy v:version', ['version' => $this->version_number]);
    }
}
