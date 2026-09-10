<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\ModuleListExport;
use App\Modules\Identity\Services\ModuleListExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleListExportIndexController extends AbstractApiController
{
    public function __invoke(Request $request, ModuleListExportService $exports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) ($validated['limit'] ?? 50);

        $rows = ModuleListExport::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static fn (ModuleListExport $row): array => $exports->present($row))
            ->values()
            ->all();

        return $this->ok($rows);
    }
}
