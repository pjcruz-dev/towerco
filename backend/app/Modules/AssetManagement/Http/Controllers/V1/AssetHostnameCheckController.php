<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Support\AssetHostnamePolicy;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetHostnameCheckController extends AbstractApiController
{
    public function __invoke(Request $request, AssetRegistryService $registry): JsonResponse
    {
        abort_unless($request->user()?->can('assets:manage'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'asset_id' => ['nullable', 'uuid'],
            'hostname' => ['nullable', 'string', 'max:64'],
        ]);
        $employee = TenantUser::query()->findOrFail($data['employee_id']);
        $code = $registry->departmentCodeFor($employee->department);
        if ($code === null) {
            return $this->error(__('No department code is mapped for :department. Add one before generating a computer name.', ['department' => (string) $employee->department]));
        }
        $suggested = AssetHostnamePolicy::suggest($employee, $code->code);
        $hostname = isset($data['hostname']) && $data['hostname'] !== '' ? AssetHostnamePolicy::assertLength($data['hostname']) : $suggested;
        $registry->assertHostnameAvailable($hostname, $data['asset_id'] ?? null);

        return $this->ok(['hostname' => $hostname, 'suggested' => $suggested]);
    }
}
