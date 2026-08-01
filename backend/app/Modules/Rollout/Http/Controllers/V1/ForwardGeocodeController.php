<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Http\Requests\ForwardGeocodeRequest;
use App\Modules\Rollout\Services\ReverseGeocodeService;
use Illuminate\Http\JsonResponse;

final class ForwardGeocodeController extends AbstractApiController
{
    public function __invoke(ForwardGeocodeRequest $request, ReverseGeocodeService $service): JsonResponse
    {
        $data = $request->validated();

        $result = $service->forward((string) $data['query']);

        return $this->ok($result->toArray());
    }
}
