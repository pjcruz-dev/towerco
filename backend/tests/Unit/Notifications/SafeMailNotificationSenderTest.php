<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\EApproval\Notifications\EApprovalMailTestNotification;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class SafeMailNotificationSenderTest extends TestCase
{
    public function test_send_now_logs_and_swallows_transport_errors(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(static function (string $message, array $context): bool {
                return str_contains($message, 'Module notification mail failed')
                    && str_contains((string) ($context['error'] ?? ''), 'Too many emails');
            });

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('550 Too many emails per second'));

        $user = new TenantUser([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        SafeMailNotificationSender::sendNow(
            [$user],
            new EApprovalMailTestNotification('test-tenant'),
        );

        $this->assertTrue(true);
    }
}
