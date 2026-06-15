<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalInAppNotificationService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Events\TenantNotificationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantNotificationBroadcastTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_in_app_notify_dispatches_tenant_notification_created(): void
    {
        Event::fake([TenantNotificationCreated::class]);

        tenancy()->initialize($this->testTenant);

        $recipient = TenantUser::query()->firstOrFail();
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Broadcast Test Form',
            'status' => 'published',
        ]);
        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $recipient->id,
            'status' => 'pending',
            'document_no' => 'EA-BROADCAST-1',
        ]);

        app(EApprovalInAppNotificationService::class)->notify(
            (string) $recipient->id,
            'approval_assigned',
            (string) $submission->id,
            'You have a new approval task.',
            $submission,
        );

        tenancy()->end();

        Event::assertDispatched(TenantNotificationCreated::class, function (TenantNotificationCreated $event) use ($recipient): bool {
            return $event->userId === (string) $recipient->id
                && $event->module === 'e_approval'
                && $event->category === 'action';
        });
    }
}
