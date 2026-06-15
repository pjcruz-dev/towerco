<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use Carbon\CarbonInterface;

final class TicketingSlaCalculator
{
    /** @var array<string, float> */
    private const PRIORITY_MULTIPLIERS = [
        TicketingTicket::PRIORITY_URGENT => 0.25,
        TicketingTicket::PRIORITY_HIGH => 0.5,
        TicketingTicket::PRIORITY_NORMAL => 1.0,
        TicketingTicket::PRIORITY_LOW => 2.0,
    ];

    public function __construct(
        private readonly TicketingSettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->getBool(TicketingSettingsService::SLA_ENABLED, true);
    }

    public function responseMinutesFor(string $priority): int
    {
        $base = $this->settings->getInt(TicketingSettingsService::SLA_RESPONSE_MINUTES, 480);

        return max(1, (int) round($base * $this->multiplierFor($priority)));
    }

    public function escalationMinutesFor(string $priority): int
    {
        $base = $this->settings->getInt(TicketingSettingsService::SLA_ESCALATION_MINUTES, 1440);

        return max(1, (int) round($base * $this->multiplierFor($priority)));
    }

    public function dueAt(TicketingTicket $ticket): ?CarbonInterface
    {
        if (! $this->isEnabled() || $ticket->created_at === null) {
            return null;
        }

        if (! in_array($ticket->status, [
            TicketingTicket::STATUS_OPEN,
            TicketingTicket::STATUS_IN_PROGRESS,
        ], true)) {
            return null;
        }

        return $ticket->created_at->copy()->addMinutes($this->escalationMinutesFor((string) $ticket->priority));
    }

    /**
     * @return 'on_track'|'at_risk'|'breached'|null
     */
    public function statusFor(TicketingTicket $ticket): ?string
    {
        if (! $this->isEnabled() || $ticket->created_at === null) {
            return null;
        }

        if (! in_array($ticket->status, [
            TicketingTicket::STATUS_OPEN,
            TicketingTicket::STATUS_IN_PROGRESS,
        ], true)) {
            return null;
        }

        $now = now();
        $escalationAt = $ticket->created_at->copy()->addMinutes($this->escalationMinutesFor((string) $ticket->priority));
        if ($now->greaterThanOrEqualTo($escalationAt) || $ticket->sla_escalated_at !== null) {
            return 'breached';
        }

        $responseAt = $ticket->created_at->copy()->addMinutes($this->responseMinutesFor((string) $ticket->priority));
        if ($now->greaterThanOrEqualTo($responseAt) || $ticket->sla_reminder_sent_at !== null) {
            return 'at_risk';
        }

        return 'on_track';
    }

    private function multiplierFor(string $priority): float
    {
        return self::PRIORITY_MULTIPLIERS[$priority] ?? 1.0;
    }
}
