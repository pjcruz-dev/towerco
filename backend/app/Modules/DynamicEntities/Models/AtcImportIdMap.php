<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AtcImportIdMap extends Model
{
    use HasUuids;

    protected $table = 'atc_import_id_maps';

    protected $fillable = [
        'source_table',
        'source_id',
        'target_type',
        'target_uuid',
    ];
}
