<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Services\TenantFileStorageService;
use App\Modules\Rollout\Support\RolloutFileContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutFileStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantFileStorageService $storage,
    ): JsonResponse {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'context' => ['required', 'string', 'in:'.implode(',', RolloutFileContext::all())],
            'rollout_id' => ['required', 'uuid', 'exists:rollout_programs,id'],
        ]);

        abort_unless(
            $request->user()?->can(RolloutFileContext::permissionFor($data['context'])),
            403,
        );

        /** @var TenantUser $user */
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->findOrFail($data['rollout_id']);

        $file = $storage->store(
            $request->file('file'),
            $data['context'],
            $rollout,
            $user,
        );

        return $this->created($storage->present($file));
    }
}
