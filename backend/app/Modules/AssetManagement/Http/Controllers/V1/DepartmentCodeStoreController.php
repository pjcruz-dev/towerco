<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\DepartmentCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DepartmentCodeStoreController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('assets:settings:manage'), 403);
        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:8'],
        ]);
        $name = trim($data['department_name']);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code']) ?? '');
        $existing = DepartmentCode::query()->where('department_name', $name)->first();
        if ($existing instanceof DepartmentCode) {
            $existing->code = $code;
            $existing->save();

            return $this->ok($existing->toArray());
        }
        $record = DepartmentCode::query()->create([
            'id' => (string) Str::uuid(),
            'department_name' => $name,
            'code' => $code,
        ]);

        return $this->created($record->toArray());
    }
}
