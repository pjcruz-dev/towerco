<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use Illuminate\Http\JsonResponse;

class CentralHealthController extends AbstractApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->ok([
            'status' => 'ok',
            'context' => 'central',
            'version' => config('toweros.api.current_version'),
        ]);
    }
}
