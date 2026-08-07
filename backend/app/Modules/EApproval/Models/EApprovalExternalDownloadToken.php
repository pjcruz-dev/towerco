<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalExternalDownloadToken extends Model
{
    use HasUuids;

    protected $table = 'e_approval_external_download_tokens';

    protected $fillable = [
        'id',
        'submission_id',
        'attachment_id',
        'form_outbound_file_id',
        'token_hash',
        'expires_at',
        'downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<EApprovalAttachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(EApprovalAttachment::class, 'attachment_id');
    }

    /** @return BelongsTo<EApprovalFormOutboundFile, $this> */
    public function formOutboundFile(): BelongsTo
    {
        return $this->belongsTo(EApprovalFormOutboundFile::class, 'form_outbound_file_id');
    }
}
