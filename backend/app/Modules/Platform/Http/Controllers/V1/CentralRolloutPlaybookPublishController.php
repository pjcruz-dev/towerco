<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutPlaybookPublishController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutPlaybookCatalogService $catalog,
    ): JsonResponse {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:32'],
        ]);

        $version = $catalog->publishVersion($data['version']);

        return $this->ok([
            'id' => $version->id,
            'version' => $version->version,
            'name' => $version->name,
            'published_at' => $version->published_at?->toIso8601String(),
        ]);
    }
}
