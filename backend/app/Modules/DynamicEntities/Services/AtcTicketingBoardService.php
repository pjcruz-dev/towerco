<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;
use Carbon\Carbon;

/**
 * ATC Dynamic Ticketing ops board over site_tickets dyn_records.
 * Separate from native /ticketing helpdesk.
 */
final class AtcTicketingBoardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $tickets = DynReportSupport::recordsForSlug('site_tickets');
        if ($tickets->isEmpty()) {
            $entityExists = \App\Modules\DynamicEntities\Models\DynEntity::query()
                ->where('slug', 'site_tickets')
                ->exists();

            return $this->empty(
                $entityExists
                    ? 'No site tickets yet. Create records under Site Tickets or import when a dump includes them.'
                    : 'Run php artisan atc:seed-ticketing-pack --tenant=<uuid> to create the ATC ticketing entities.'
            );
        }

        $sites = DynReportSupport::siteIndex();
        $now = now();

        $byStatus = [];
        $byPriority = [];
        $byType = [];
        $open = 0;
        $overdue = 0;
        $criticalOpen = 0;
        $resolvedMtd = 0;
        $monthStart = $now->copy()->startOfMonth();
        $recent = [];

        foreach ($tickets as $ticket) {
            $values = $ticket->values_json ?? [];
            $status = (string) ($ticket->status ?? $values['status'] ?? 'Open');
            $priority = (string) ($values['priority'] ?? 'Medium');
            $type = (string) ($values['ticket_type'] ?? 'Incident');

            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;
            $byType[$type] = ($byType[$type] ?? 0) + 1;

            $statusLower = strtolower($status);
            $isClosed = str_contains($statusLower, 'closed') || str_contains($statusLower, 'resolved');
            if (! $isClosed) {
                $open++;
                if (str_contains(strtolower($priority), 'critical')) {
                    $criticalOpen++;
                }
                $due = DynReportSupport::parseDate($values['due_at'] ?? null);
                if ($due !== null && $due->lt($now->copy()->startOfDay())) {
                    $overdue++;
                }
            } else {
                $resolvedAt = DynReportSupport::parseDate($values['resolved_at'] ?? null)
                    ?? ($ticket->updated_at instanceof Carbon ? $ticket->updated_at->copy()->startOfDay() : null);
                if ($resolvedAt !== null && $resolvedAt->gte($monthStart)) {
                    $resolvedMtd++;
                }
            }

            $siteId = (string) ($values['tower_site_id'] ?? '');
            $siteTitle = $sites[$siteId]['title'] ?? ($siteId !== '' ? DynReportSupport::shortId($siteId) : '—');

            $recent[] = [
                'id' => $ticket->id,
                'title' => $ticket->title ?: (string) ($values['subject'] ?? $values['ticket_number'] ?? 'Ticket'),
                'ticket_number' => (string) ($values['ticket_number'] ?? ''),
                'status' => $status,
                'priority' => $priority,
                'ticket_type' => $type,
                'site' => is_string($siteTitle) ? $siteTitle : '—',
                'assigned_to' => (string) ($values['assigned_to'] ?? ''),
                'due_at' => isset($values['due_at']) ? (string) $values['due_at'] : null,
                'href' => '/dynamic-entities/records/'.$ticket->id,
            ];
        }

        usort($recent, static function (array $a, array $b): int {
            $prio = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
            $pa = $prio[$a['priority']] ?? 9;
            $pb = $prio[$b['priority']] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) ($a['ticket_number'] ?? ''), (string) ($b['ticket_number'] ?? ''));
        });

        $attention = [];
        if ($criticalOpen > 0) {
            $attention[] = [
                'headline' => 'Critical open tickets',
                'detail' => $criticalOpen.' critical ticket(s) still open.',
                'href' => '/dynamic-entities/site_tickets',
                'tone' => 'danger',
            ];
        }
        if ($overdue > 0) {
            $attention[] = [
                'headline' => 'Overdue tickets',
                'detail' => $overdue.' open ticket(s) past due date.',
                'href' => '/dynamic-entities/site_tickets',
                'tone' => 'warning',
            ];
        }
        if ($attention === []) {
            $attention[] = [
                'headline' => 'Queue healthy',
                'detail' => 'No critical or overdue open site tickets.',
                'href' => '/dynamic-entities/site_tickets',
                'tone' => 'success',
            ];
        }

        return [
            'message' => null,
            'generated_at' => now()->toIso8601String(),
            'kpis' => [
                'total' => $tickets->count(),
                'open' => $open,
                'overdue' => $overdue,
                'critical_open' => $criticalOpen,
                'resolved_mtd' => $resolvedMtd,
            ],
            'by_status' => $this->toChart($byStatus),
            'by_priority' => $this->toChart($byPriority),
            'by_type' => $this->toChart($byType),
            'attention' => $attention,
            'recent' => array_slice($recent, 0, 25),
            'quick_links' => [
                ['label' => 'All site tickets', 'href' => '/dynamic-entities/site_tickets'],
                ['label' => 'New ticket', 'href' => '/dynamic-entities/site_tickets/new'],
                ['label' => 'Categories', 'href' => '/dynamic-entities/ticket_categories'],
                ['label' => 'Activities', 'href' => '/dynamic-entities/ticket_activities'],
                ['label' => 'Native IT helpdesk', 'href' => '/ticketing'],
            ],
        ];
    }

    /**
     * @param  array<string, int|float>  $counts
     * @return list<array{key: string, label: string, value: float|int}>
     */
    private function toChart(array $counts, int $limit = 12): array
    {
        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn (int|float $value, string $label): array => [
                'key' => $label !== '' ? $label : '—',
                'label' => $label !== '' ? $label : '—',
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $message): array
    {
        return [
            'message' => $message,
            'generated_at' => now()->toIso8601String(),
            'kpis' => [
                'total' => 0,
                'open' => 0,
                'overdue' => 0,
                'critical_open' => 0,
                'resolved_mtd' => 0,
            ],
            'by_status' => [],
            'by_priority' => [],
            'by_type' => [],
            'attention' => [],
            'recent' => [],
            'quick_links' => [
                ['label' => 'Site tickets', 'href' => '/dynamic-entities/site_tickets'],
                ['label' => 'Native IT helpdesk', 'href' => '/ticketing'],
            ],
        ];
    }
}
