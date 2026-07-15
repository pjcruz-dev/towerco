<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqVendor extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_vendors';

    protected $fillable = [
        'rfq_id',
        'vendor_id',
        'invitation_status',
        'invited_at',
        'responded_at',
        'invitation_token_hash',
        'invitation_token_encrypted',
        'invitation_token_expires_at',
        'invitation_email',
        'invitation_sent_at',
        'invitation_opened_at',
        'submitted_via',
        'portal_contact_name',
        'reminder_log_json',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
            'invitation_token_expires_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_opened_at' => 'datetime',
            'reminder_log_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'rfq_id');
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'vendor_id');
    }
}
