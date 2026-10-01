<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynHtmlReport extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'dyn_html_reports';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'html_source',
        'css_source',
        'js_source',
        'builder_json',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'builder_json' => 'array',
        ];
    }
}
