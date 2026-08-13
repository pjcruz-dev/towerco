<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stored WebAuthn / passkey public credential for a tenant user.
 *
 * @property string $id
 * @property string $user_id
 * @property string $credential_id
 * @property string $public_key
 * @property int $sign_count
 * @property array<int, string>|null $transports
 * @property string|null $attestation_format
 * @property string|null $aaguid
 * @property string|null $label
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class WebAuthnCredential extends Model
{
    protected $connection = 'tenant';

    protected $table = 'webauthn_credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'attestation_format',
        'aaguid',
        'label',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
            'transports' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /**
     * @return array{id: string, label: string|null, transports: list<string>|null, attestation_format: string|null, last_used_at: string|null, created_at: string|null}
     */
    public function toPublicRow(): array
    {
        return [
            'id' => (string) $this->id,
            'label' => $this->label,
            'transports' => is_array($this->transports) ? array_values($this->transports) : null,
            'attestation_format' => $this->attestation_format,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
