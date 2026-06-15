<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingAssignableUsersController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ticketing:tickets:manage'), 403);

        $users = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (TenantUser $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();

        return $this->ok($users);
    }
}
