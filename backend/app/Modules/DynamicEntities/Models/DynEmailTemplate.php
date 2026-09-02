<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynEmailTemplate extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'dyn_email_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'entity_slug',
        'subject',
        'body_html',
        'body_text',
        'default_to',
        'cc',
        'bcc',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
