<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRecordImportService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordImportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRecordImportService $import,
        DynRoleAccessService $access,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:records:manage'), 403);
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        abort_unless($access->canEntity($user, (string) $model->slug, 'create'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'column_map' => ['required', 'string'],
            'upsert_field' => ['nullable', 'string', 'max:64'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $decoded = json_decode($data['column_map'], true);
        if (! is_array($decoded)) {
            return $this->error('column_map must be a JSON object.', 422);
        }

        /** @var array<string, string> $map */
        $map = [];
        foreach ($decoded as $k => $v) {
            $map[(string) $k] = is_scalar($v) ? (string) $v : '__ignore__';
        }

        $dryRun = $request->boolean('dry_run');

        $result = $import->import(
            $model,
            $request->file('file'),
            $map,
            isset($data['upsert_field']) ? (string) $data['upsert_field'] : null,
            $user,
            $dryRun,
        );

        return $this->ok($result);
    }
}
