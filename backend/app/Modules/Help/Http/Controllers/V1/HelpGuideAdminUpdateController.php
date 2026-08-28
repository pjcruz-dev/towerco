<?php

declare(strict_types=1);

namespace App\Modules\Help\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelpGuideAdminUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $slug, HelpGuideService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:200000'],
            'role' => ['sometimes', 'string', 'in:requestor,approver,all'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        $guide = $service->findBySlugOrFail($slug);
        $updated = $service->update($request->user(), $guide, $data);

        return $this->ok($service->asDetail($updated));
    }
}
