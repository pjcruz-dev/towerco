<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentActivity;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

final class DocumentActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        Document $document,
        string $event,
        ?TenantUser $actor = null,
        array $metadata = [],
    ): void {
        DocumentActivity::query()->create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'site_id' => $document->site_id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
