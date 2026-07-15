<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentRolloutLinkOptionsService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteRolloutOptionsController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Site $site,
        DocumentRolloutLinkOptionsService $options,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('documents:manage') && $request->user()?->can('project_one:rollout:view'),
            403,
        );

        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:128'],
        ]);

        return $this->ok($options->forSite($site, (string) ($validated['search'] ?? '')));
    }
}
