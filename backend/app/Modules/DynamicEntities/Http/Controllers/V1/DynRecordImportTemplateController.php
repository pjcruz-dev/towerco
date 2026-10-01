<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRecordImportService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DynRecordImportTemplateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRecordImportService $import,
        DynRoleAccessService $access,
    ): StreamedResponse {
        abort_unless($request->user()?->can('dynamic_entities:records:manage'), 403);
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        abort_unless($access->canEntity($user, (string) $model->slug, 'create'), 403);

        $template = $import->buildTemplate($model);

        return response()->streamDownload(static function () use ($template): void {
            echo $template['csv'];
        }, $template['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
