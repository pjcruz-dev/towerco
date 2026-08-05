<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantEnvironmentHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantEnvironmentHandoffRedeemController extends AbstractApiController
{
    public function __invoke(Request $request, TenantEnvironmentHandoffService $handoff): JsonResponse
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException(__('Tenant context is required.'));
        }

        $data = $request->validate([
            'ticket' => ['required', 'string', 'min:32', 'max:128'],
        ]);

        return $this->ok($handoff->redeemAtomically(
            $tenant,
            (string) $data['ticket'],
            $request->ip(),
            $request->userAgent(),
        ));
    }
}
