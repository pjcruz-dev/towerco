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
        $rolesIdx = array_search('roles', $columns, true);

        if ($emailIdx === false || $nameIdx === false) {
            fclose($handle);

            return $this->error(__('CSV must include email and name columns.'), 422);
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (! is_array($line)) {
                continue;
            }
            $roleRaw = 'viewer';
            if ($rolesIdx !== false) {
                $roleRaw = $this->joinCsvRoleCells($line, $rolesIdx);
            } elseif ($roleIdx !== false) {
                $roleRaw = $this->joinCsvRoleCells($line, $roleIdx);
            }
            if ($roleRaw === '') {
                $roleRaw = 'viewer';
            }

            $rows[] = [
                'email' => strtolower(trim((string) ($line[$emailIdx] ?? ''))),
                'name' => trim((string) ($line[$nameIdx] ?? '')),
                'role' => $roleRaw,
            ];
        }
        fclose($handle);

        $result = $service->importRows($rows);

        return $this->ok([
            ...$result,
            'hint' => __(
                'Users are matched by email (case-insensitive). Microsoft sign-in reuses imported accounts — duplicate CSV rows and matching emails are skipped. Roles from import are kept unless Entra group mapping adds roles on sign-in. Multiple roles: comma-separate in the role column (quote the cell in Excel).',
            ),
        ]);
    }

    /**
     * Read the role cell and any trailing fragments (unquoted Excel multi-role exports).
     *
     * @param  list<int|string|null>  $line
     */
    private function joinCsvRoleCells(array $line, int $startIdx): string
    {
        $parts = [];
        for ($i = $startIdx; $i < count($line); $i++) {
            $part = trim((string) ($line[$i] ?? ''));
            if ($part === '') {
                continue;
            }
            $parts[] = $part;
        }

        return implode(',', $parts);
    }
}
