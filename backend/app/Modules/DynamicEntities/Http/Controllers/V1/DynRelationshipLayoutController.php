<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynRelationshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRelationshipLayoutController extends AbstractApiController
{
    public function __invoke(Request $request, DynRelationshipService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        if ($request->boolean('reset')) {
            $service->clearLayout();

            return $this->ok([
                'positions' => [],
                'viewport' => null,
            ]);
        }

        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['array'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
            'viewport' => ['nullable', 'array'],
            'reset' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->saveLayout(
            $data['positions'],
            $data['viewport'] ?? null,
        ));
    }
}
