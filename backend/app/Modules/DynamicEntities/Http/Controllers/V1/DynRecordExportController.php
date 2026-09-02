<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DynRecordExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse|StreamedResponse {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        abort_unless($access->canEntity($user, (string) $model->slug, 'view'), 403);
        abort_unless($access->canEntity($user, (string) $model->slug, 'export') || $user->can('dynamic_entities:view'), 403);

        $ids = $request->query('ids');
        $idList = null;
        if (is_string($ids) && $ids !== '') {
            $idList = array_values(array_filter(array_map('trim', explode(',', $ids))));
        } elseif (is_array($ids)) {
            $idList = array_map('strval', $ids);
        }

        $payload = $service->exportRows($model, $idList, [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ]);

        if ($request->boolean('summary_only')) {
            return $this->ok([
                'summary' => $payload['summary'],
                'headers' => $payload['headers'],
                'row_count' => count($payload['rows']),
            ]);
        }

        $filename = $model->slug.'-export-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $payload['headers']);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
