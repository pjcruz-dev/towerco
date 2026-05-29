<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutPolicyBundleStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutPolicyBundleService $service): JsonResponse
    {
        $data = $request->validate([
            'playbook_version_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        /** @var RolloutPlaybookVersion $version */
        $version = RolloutPlaybookVersion::query()->findOrFail($data['playbook_version_id']);

        $bundle = $service->createDraft($version, $data['code'], $data['name']);

        return $this->ok($service->present($bundle), 201);
    }
}
