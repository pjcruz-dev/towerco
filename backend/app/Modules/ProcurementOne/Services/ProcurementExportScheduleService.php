<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class ProcurementExportScheduleService
{
    public const SETTINGS_KEY = 'export_schedule';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     frequency: string,
     *     day_of_month: int,
     *     hour: int,
     *     recipients: list<string>,
     *     period: string,
     *     last_run_at: string|null
     * }
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);

        return $this->normalize(is_array($raw) ? $raw : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{enabled: bool, frequency: string, day_of_month: int, hour: int, recipients: list<string>, period: string, last_run_at: string|null}
     */
    public function validateAndNormalize(array $input): array
    {
        $existing = $this->policy();
        $normalized = $this->normalize(array_merge($existing, $input));

        if ($normalized['enabled'] && $normalized['recipients'] === []) {
            throw ValidationException::withMessages([
                'export_schedule.recipients' => [__('At least one finance recipient email is required when scheduled exports are enabled.')],
            ]);
        }

        return $normalized;
    }

    public function shouldRunNow(?Carbon $now = null): bool
    {
        $policy = $this->policy();
        if (! $policy['enabled'] || $policy['frequency'] !== 'monthly') {
            return false;
        }

        $now ??= Carbon::now();

        if ((int) $now->day !== (int) $policy['day_of_month'] || (int) $now->hour !== (int) $policy['hour']) {
            return false;
        }

        if ($policy['last_run_at'] !== null) {
            $lastRun = Carbon::parse($policy['last_run_at']);
            if ($lastRun->isSameMonth($now)) {
                return false;
            }
        }

        return true;
    }

    public function markRunComplete(?Carbon $ranAt = null): void
    {
        $policy = $this->policy();
        $policy['last_run_at'] = ($ranAt ?? Carbon::now())->toIso8601String();
        $this->settings->setJson(self::SETTINGS_KEY, $policy);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{enabled: bool, frequency: string, day_of_month: int, hour: int, recipients: list<string>, period: string, last_run_at: string|null}
     */
    private function normalize(array $input): array
    {
        $frequency = strtolower(trim((string) ($input['frequency'] ?? 'monthly')));
        if (! in_array($frequency, ['monthly'], true)) {
            $frequency = 'monthly';
        }

        $period = strtolower(trim((string) ($input['period'] ?? 'previous_month')));
        if (! in_array($period, ['previous_month', 'current_month'], true)) {
            $period = 'previous_month';
        }

        $recipients = [];
        foreach ((array) ($input['recipients'] ?? []) as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
        $recipients = array_values(array_unique($recipients));

        $day = (int) ($input['day_of_month'] ?? 1);
        if ($day < 1 || $day > 28) {
            $day = 1;
        }

        $hour = (int) ($input['hour'] ?? 6);
        if ($hour < 0 || $hour > 23) {
            $hour = 6;
        }

        return [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'frequency' => $frequency,
            'day_of_month' => $day,
            'hour' => $hour,
            'recipients' => $recipients,
            'period' => $period,
            'last_run_at' => isset($input['last_run_at']) ? (string) $input['last_run_at'] : null,
        ];
    }
}
