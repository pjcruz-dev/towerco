<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Services\RoleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RoleCompareController extends AbstractApiController
{
    public function __invoke(Request $request, RoleCatalogService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('role:manage') || $request->user()?->can('user:manage'),
            403,
        );

        $data = $request->validate([
            'left' => ['required', 'integer', 'min:1'],
            'right' => ['required', 'integer', 'min:1', 'different:left'],
        ]);

        $left = TenantRole::query()->find($data['left']);
        $right = TenantRole::query()->find($data['right']);

        if ($left === null || $right === null) {
            throw ValidationException::withMessages([
                'role' => [__('Role not found.')],
            ]);
        }

        return $this->ok($service->compare($left, $right));
    }
}
