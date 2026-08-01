<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Http\Requests\ReverseGeocodeRequest;
use App\Modules\Rollout\Services\ReverseGeocodeService;
use Illuminate\Http\JsonResponse;

final class ReverseGeocodeController extends AbstractApiController
{
    public function __invoke(ReverseGeocodeRequest $request, ReverseGeocodeService $service): JsonResponse
    {
        $data = $request->validated();

        $result = $service->reverse(
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return $this->ok($result->toArray());
    }
}
