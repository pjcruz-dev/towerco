<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Core\Support\ExcelWorkbookWriter;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class DynRecordExportWorkbookController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRoleAccessService $access,
    ): Response {
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

        $raw = $request->input('rows');
        if (! is_array($raw) || $raw === [] || count($raw) > 20000) {
            throw ValidationException::withMessages([
                'rows' => 'Export must include between 1 and 20,000 rows.',
            ]);
        }

        $rows = [];
        foreach ($raw as $line) {
            if (! is_array($line) || count($line) > 80) {
                throw ValidationException::withMessages([
                    'rows' => 'Each export row must be a list of at most 80 cells.',
                ]);
            }
            $cells = [];
            foreach ($line as $cell) {
                if ($cell !== null && ! is_scalar($cell)) {
                    $cells[] = '';

                    continue;
                }
                $cells[] = mb_substr((string) ($cell ?? ''), 0, 2000);
            }
            $rows[] = $cells;
        }

        $filename = $this->filename((string) $request->input('filename', $model->slug.'-export.xlsx'));
        $writer = new ExcelWorkbookWriter;
        $writer->addSheet($model->name !== '' ? (string) $model->name : 'Export', $rows);

        return response($writer->toBinaryString(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function filename(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'export.xlsx';
        if (! str_ends_with(strtolower($clean), '.xlsx')) {
            $clean .= '.xlsx';
        }

        return $clean;
    }
}
