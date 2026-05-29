<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Platform\Services\OperationalAcronymService;
use App\Modules\Platform\Support\OperationalAcronymDefaults;
use Illuminate\Console\Command;

class SyncOperationalAcronymDefaultsCommand extends Command
{
    protected $signature = 'operational-acronyms:sync-defaults';

    protected $description = 'Upsert TowerOS default operational acronyms into the central glossary';

    public function handle(OperationalAcronymService $service): int
    {
        $count = $service->syncDefaults(OperationalAcronymDefaults::all());
        $this->info("Synced {$count} operational acronyms.");

        return self::SUCCESS;
    }
}
