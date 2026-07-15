<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EApprovalApprovalPolicy extends Model
{
    use HasUuids;

    protected $table = 'e_approval_approval_policies';

    protected $fillable = [
        'key',
        'name',
        'description',
    ];

    /** @return HasMany<EApprovalApprovalPolicyVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EApprovalApprovalPolicyVersion::class, 'policy_id');
    }

    /** @return HasOne<EApprovalApprovalPolicyVersion, $this> */
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(EApprovalApprovalPolicyVersion::class, 'policy_id')
            ->where('status', 'published')
            ->latest('version_number');
    }
}
