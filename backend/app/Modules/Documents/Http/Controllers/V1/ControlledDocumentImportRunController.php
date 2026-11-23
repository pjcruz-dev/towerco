<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Http\Controllers\V1\Concerns\DecodesControlledDocumentImportRequest;
use App\Modules\Documents\Services\ControlledDocumentImportService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentImportRunController extends AbstractApiController
{
    use DecodesControlledDocumentImportRequest;

    public function __invoke(Request $request, ControlledDocumentImportService $import): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('documents:controlled:import'), 403);
        abort_unless(app(ControlledDocumentRoleFieldAccess::class)->allows($user, 'import'), 403);

        $payload = $this->decodeControlledDocumentImport($request, allowFiles: true);
        $available = $import->availablePaths(
            $payload['archive'],
            $payload['available_paths'],
            $payload['files'],
        );

        $files = $payload['files'];
        if ($payload['archive'] !== null) {
            $preview = $import->importMapped(
                csv: $payload['file'],
                columnMap: $payload['column_map'],
                actor: $request->user(),
                availablePaths: $available,
                filesByPath: [],
                write: false,
            );
            $needed = [];
            foreach ($preview['rows'] as $row) {
                if ($row['file_status'] === 'matched' && is_string($row['matched_path'])) {
                    $needed[] = $row['matched_path'];
                }
            }
            $files = $import->filesFromArchive($payload['archive'], $needed);
        }

        return $this->ok($import->importMapped(
            csv: $payload['file'],
            columnMap: $payload['column_map'],
            actor: $request->user(),
            availablePaths: $available,
            filesByPath: $files,
            write: true,
        ));
    }
}
