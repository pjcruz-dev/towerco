<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Services\TenantFileStorageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RolloutFileShowController extends AbstractApiController
{
    public function __invoke(
        TenantRolloutFile $file,
        TenantFileStorageService $storage,
    ): StreamedResponse {
        abort_unless(auth()->user()?->can('project_one:rollout:view'), 403);

        return $storage->downloadResponse($file);
    }
}
