<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Sends module notification mail without failing the parent HTTP workflow when SMTP is down,
 * misconfigured, or rate-limited (e.g. Mailtrap free tier).
 */
final class SafeMailNotificationSender
{
    /**
     * @param  iterable<int, mixed>  $notifiables
     */
    public static function sendAfterResponse(iterable $notifiables, Notification $notification): void
    {
        dispatch(static function () use ($notifiables, $notification): void {
            self::sendNow($notifiables, $notification);
        })->afterResponse();
    }

    /**
     * @param  iterable<int, mixed>  $notifiables
     */
    public static function sendNow(iterable $notifiables, Notification $notification): void
    {
        try {
            NotificationFacade::send($notifiables, $notification);
        } catch (Throwable $exception) {
            Log::warning('Module notification mail failed; workflow was not rolled back.', [
                'notification' => $notification::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
