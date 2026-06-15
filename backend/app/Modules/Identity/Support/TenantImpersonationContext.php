<?php



declare(strict_types=1);



namespace App\Modules\Identity\Support;



use App\Modules\Identity\Models\TenantUser;



/**

 * Active impersonation session: effective user is the target.

 * Actor may be a tenant admin or a central platform operator.

 */

final readonly class TenantImpersonationContext

{

    /**

     * @param  array{id: string, name: string, email: string, source?: string}|null  $platformImpersonator

     */

    public function __construct(

        public string $sessionId,

        public ?TenantUser $tenantImpersonator = null,

        public ?array $platformImpersonator = null,

    ) {

        if ($tenantImpersonator === null && $platformImpersonator === null) {

            throw new \InvalidArgumentException('Impersonation context requires a tenant or platform actor.');

        }

    }



    public function impersonatorUserId(): ?string

    {

        if ($this->tenantImpersonator !== null) {

            return (string) $this->tenantImpersonator->getKey();

        }



        return $this->platformImpersonator['id'] ?? null;

    }

}

