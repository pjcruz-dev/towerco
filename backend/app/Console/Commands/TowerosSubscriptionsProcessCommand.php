<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;
use Illuminate\Console\Command;

final class TowerosSubscriptionsProcessCommand extends Command
{
    protected $signature = 'toweros:subscriptions:process';

    protected $description = 'Expire trials and lock tenants after past-due grace (central billing lifecycle).';

    public function handle(TenantSubscriptionLifecycleService $lifecycle): int
    {
        $result = $lifecycle->processScheduledTransitions();

        $this->info("Trials transitioned: {$result['trials_expired']}");
        $this->info("Past-due tenants locked: {$result['past_due_locked']}");

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
