<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserImportController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return $this->error(__('Could not read upload.'), 422);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return $this->error(__('CSV header row is required.'), 422);
        }

        $columns = array_map(static fn ($col) => strtolower(trim((string) $col)), $header);
        $emailIdx = array_search('email', $columns, true);
        $nameIdx = array_search('name', $columns, true);
        $roleIdx = array_search('role', $columns, true);

        if ($emailIdx === false || $nameIdx === false) {
            fclose($handle);

            return $this->error(__('CSV must include email and name columns.'), 422);
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (! is_array($line)) {
                continue;
            }
            $rows[] = [
                'email' => trim((string) ($line[$emailIdx] ?? '')),
                'name' => trim((string) ($line[$nameIdx] ?? '')),
                'role' => $roleIdx !== false ? trim((string) ($line[$roleIdx] ?? 'viewer')) : 'viewer',
            ];
        }
        fclose($handle);

        $result = $service->importRows($rows);

        return $this->ok($result);
    }
}
