<?php



declare(strict_types=1);



namespace App\Core\Http\Middleware;



use App\Core\Exceptions\TenantSubscriptionReadOnlyException;

use App\Core\Exceptions\TenantSubscriptionSuspendedException;

use App\Models\Tenant;

use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;

use Closure;

use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;



final class EnsureTenantSubscriptionAccess

{

    public function __construct(

        private readonly TenantSubscriptionLifecycleService $subscriptions,

    ) {}



    /**

     * @param  Closure(Request): Response  $next

     */

    public function handle(Request $request, Closure $next): Response

    {

        if ($this->isExempt($request)) {

            return $next($request);

        }



        $tenant = tenant();

        if (! $tenant instanceof Tenant) {

            return $next($request);

        }



        $snapshot = $this->subscriptions->snapshot($tenant);



        if ($snapshot['access_mode'] === 'read_only' && $this->isMutating($request)) {

            throw new TenantSubscriptionReadOnlyException(

                (string) ($snapshot['message'] ?? __('This organization is in read-only mode.')),

            );

        }



        if (! $snapshot['access_allowed']) {

            throw new TenantSubscriptionSuspendedException(

                (string) ($snapshot['message'] ?? __('Subscription access is suspended.')),

            );

        }



        return $next($request);

    }



    private function isExempt(Request $request): bool

    {

        $path = trim($request->path(), '/');



        /** @var list<string> $prefixes */

        $prefixes = config('billing.subscription_api_exempt_prefixes', [

            'api/v1/auth/',

            'api/v1/e-approval/health',

            'api/v1/admin/billing',

            'api/v1/health',

        ]);



        foreach ($prefixes as $prefix) {

            if (str_starts_with($path, $prefix)) {

                return true;

            }

        }



        return false;

    }



    private function isMutating(Request $request): bool

    {

        return ! in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);

    }

}

