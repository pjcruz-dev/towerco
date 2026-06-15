<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Notifications\TicketingMailTestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class TicketingSettingsTestEmailService
{
    /**
     * @return array{sent_to: string, mailer: string}
     */
    public function sendToUser(TenantUser $user): array
    {
        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => [__('Your account does not have a valid email address.')],
            ]);
        }

        $mailer = (string) config('toweros.notifications_mail_mailer', config('mail.default'));
        if ($mailer === 'log') {
            throw ValidationException::withMessages([
                'mail' => [__(
                    'Mail is set to log only. Configure TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp (Microsoft 365) or ses in the API environment.',
                )],
            ]);
        }

        Notification::send($user, new TicketingMailTestNotification($this->tenantLabel()));

        return [
            'sent_to' => $email,
            'mailer' => $mailer,
        ];
    }

    private function tenantLabel(): string
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $slug = trim((string) ($tenant->slug ?? ''));
            if ($slug !== '') {
                return $slug;
            }

            return (string) $tenant->id;
        }

        return 'unknown';
    }
}
