<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Full active-user directory for ticket managers (IT pool CRUD, create-on-behalf requester).
 */
class TicketingDirectoryUsersController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->can('ticketing:tickets:manage') || $user?->can('ticketing:settings:manage'),
            403,
        );

        $users = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (TenantUser $row) => [
                'id' => (string) $row->id,
                'name' => $row->name,
                'email' => $row->email,
            ])
            ->all();

        return $this->ok($users);
    }
}
