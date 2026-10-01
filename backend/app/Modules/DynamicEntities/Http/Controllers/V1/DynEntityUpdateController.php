<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynEntityUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, DynEntityAdminService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('dynamic_entities:entities:manage')
                || $request->user()?->can('printables:manage'),
            403,
        );

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        $canManageEntities = (bool) $request->user()?->can('dynamic_entities:entities:manage');
        $canManagePrintables = (bool) $request->user()?->can('printables:manage');

        if ($canManageEntities) {
            $data = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'module_pack' => ['sometimes', 'string', 'in:'.implode(',', DynModulePack::all())],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_active' => ['sometimes', 'boolean'],
                'related_tabs_json' => ['nullable', 'array'],
                'print_settings_json' => ['nullable', 'array'],
            ]);
        } else {
            // printables:manage may only update printable settings.
            abort_unless($canManagePrintables, 403);
            $data = $request->validate([
                'print_settings_json' => ['required', 'array'],
            ]);
        }

        $updated = $service->updateEntity($model, $data, $actor);

        return $this->ok($service->presentEntity($updated, true));
    }
}
