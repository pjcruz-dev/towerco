<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynSearchIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynSearchIndexActionController extends AbstractApiController
{
    public function __invoke(Request $request, DynSearchIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('search_index:manage'), 403);

        $data = $request->validate([
            'action' => ['required', 'string', 'in:repair,full_rebuild,rebuild_metadata,rebuild_entity'],
            'entity_slug' => ['nullable', 'string', 'max:160'],
        ]);

        $result = match ($data['action']) {
            'repair' => $service->repair(),
            'full_rebuild' => $service->fullRebuild(),
            'rebuild_metadata' => $service->rebuildMetadataOnly(),
            'rebuild_entity' => (function () use ($service, $data): array {
                $slug = trim((string) ($data['entity_slug'] ?? ''));
                abort_if($slug === '', 422, 'entity_slug is required for rebuild_entity.');

                return $service->rebuildEntityBySlug($slug);
            })(),
        };

        return $this->ok($result);
    }
}
