<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantUserExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, TenantUserAdminService $service): StreamedResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive,all'],
        ])['status'] ?? null;
        $status = $status === 'all' ? null : $status;

        $users = $service->allForExport($query['search'], $status);
        $filename = 'users-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($users): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'name',
                'email',
                'status',
                'roles',
                'deactivated_at',
                'created_at',
            ]);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->isActive() ? 'active' : 'inactive',
                    $user->getRoleNames()->join(', '),
                    $user->deactivated_at?->toIso8601String() ?? '',
                    $user->created_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
