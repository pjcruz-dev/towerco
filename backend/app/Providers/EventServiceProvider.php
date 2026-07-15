<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\ProcurementOne\Events\ProcurementDocumentApproved;
use App\Modules\ProcurementOne\Listeners\ProcurementDocumentLifecycleListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        SocialiteWasCalled::class => [
            AzureExtendSocialite::class,
        ],
        ProcurementDocumentApproved::class => [
            [ProcurementDocumentLifecycleListener::class, 'handleApproved'],
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
