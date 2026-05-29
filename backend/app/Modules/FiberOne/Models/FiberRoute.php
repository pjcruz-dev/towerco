<?php

declare(strict_types=1);

namespace App\Modules\FiberOne\Models;

use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberRoute extends Model
{
    use HasUuids;

    protected $table = 'fiber_routes';

    protected $fillable = [
        'name',
        'status',
        'from_site_id',
        'to_site_id',
        'length_km',
    ];

    protected function casts(): array
    {
        return [
            'length_km' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }
}
