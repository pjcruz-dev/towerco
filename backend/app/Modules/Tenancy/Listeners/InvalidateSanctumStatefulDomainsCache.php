<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Listeners;

use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;

final class InvalidateSanctumStatefulDomainsCache
{
    public function __construct(
        private readonly SanctumStatefulDomainResolver $resolver,
    ) {}

    public function handle(): void
    {
        $this->resolver->forget();
    }
}
