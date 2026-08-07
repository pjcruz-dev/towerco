<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalFormOutboundFile extends Model
{
    use HasUuids;

    protected $table = 'e_approval_form_outbound_files';

    protected $fillable = [
        'id',
        'form_id',
        'file_path',
        'file_name',
        'byte_size',
        'uploaded_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
        ];
    }

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'form_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(): array
    {
        return [
            'id' => (string) $this->id,
            'form_id' => (string) $this->form_id,
            'file_name' => (string) $this->file_name,
            'byte_size' => (int) $this->byte_size,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
