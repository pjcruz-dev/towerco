<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Listeners;

use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;

final class InvalidateSanctumStatefulDomainsCache
{
    public function __construct(
        private readonly SanctumStatefulDomainResolver $resolver,
        private readonly CorsAllowedOriginResolver $corsOrigins,
    ) {}

    public function handle(): void
    {
        $this->resolver->forget();
        $this->corsOrigins->forget();
    }
}
