<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Alliance demo seeding for removed Sites/ProjectOne/Rollout modules is retired.
 * Dynamic Entities / ATC packs are seeded via dedicated artisan commands.
 */
class AllianceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command !== null) {
            $this->command->warn('AllianceDemoSeeder is a no-op (legacy sites/projects/towers/procurement modules removed).');
        }
    }
}
