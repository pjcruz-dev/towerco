<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final class EntraUserManagerMatch
{
    public function __construct(
        public readonly EntraDirectoryPerson $person,
        public readonly ?EntraDirectoryPerson $manager = null,
    ) {}
}
