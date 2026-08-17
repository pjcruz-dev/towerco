<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\TenantImpersonationContextResolver;
use App\Modules\Tenancy\Services\TenantEnvironmentHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantEnvironmentHandoffMintController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantEnvironmentHandoffService $handoff,
        TenantImpersonationContextResolver $impersonationResolver,
    ): JsonResponse {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException(__('Tenant context is required.'));
        }

        if ($impersonationResolver->fromRequest($request) !== null) {
            throw ValidationException::withMessages([
                'environment' => [__('Environment switch is not available while impersonating.')],
            ]);
        }

        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        abort_unless(
            $user->can('workspace:environments:switch'),
            403,
            __('You do not have permission to switch environments.'),
        );

        $data = $request->validate([
            'environment' => ['required', 'string', 'in:local,test,staging,production'],
        ]);

        $sessionId = (string) $request->attributes->get('auth_session_id', $request->header('X-Session-Id', ''));

        return $this->ok($handoff->mint(
            $tenant,
            $user,
            (string) $data['environment'],
            $sessionId !== '' ? $sessionId : null,
        ));
    }
}
