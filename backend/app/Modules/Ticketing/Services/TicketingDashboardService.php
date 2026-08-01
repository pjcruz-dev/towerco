<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantScopedCache;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use Illuminate\Support\Facades\DB;

final class TicketingDashboardService
{
    public function __construct(
        private readonly TicketingCategoryCatalog $categories,
    ) {}

    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   recent_tickets: list<array<string, mixed>>,
     *   by_category: list<array<string, mixed>>,
     *   message: string
     * }
     */
    public function build(TenantUser $user): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');

        return TenantScopedCache::remember(
            "ticketing:dashboard:{$tenantId}:{$user->id}",
            30,
            fn (): array => $this->buildUncached($user),
        );
    }

    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   recent_tickets: list<array<string, mixed>>,
     *   by_category: list<array<string, mixed>>,
     *   message: string
     * }
     */
    private function buildUncached(TenantUser $user): array
    {
        $canManage = $user->can('ticketing:tickets:manage');
        $userId = (string) $user->id;

        $scope = static function ($query) use ($canManage, $userId) {
            return $query->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }));
        };

        $openCount = $scope(TicketingTicket::query()
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS]))
            ->count();

        $assignedToMe = TicketingTicket::query()
            ->where('assignee_id', $userId)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->count();

        $urgentCount = $scope(TicketingTicket::query()
            ->where('priority', TicketingTicket::PRIORITY_URGENT)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS]))
            ->count();

        $resolvedThisWeek = $scope(TicketingTicket::query()
            ->where('status', TicketingTicket::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->subDays(7)))
            ->count();

        $slaAtRisk = 0;
        if ($canManage) {
            // Uses the denormalized sla_status column (maintained by the SLA runner) so this
            // is a single indexed count instead of loading every active ticket into PHP.
            $slaAtRisk = TicketingTicket::query()
                ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
                ->whereIn('sla_status', ['at_risk', 'breached'])
                ->count();
        }

        $recent = $scope(TicketingTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email']))
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TicketingTicket $ticket) => $this->ticketSummary($ticket))
            ->all();

        return [
            'kpis' => [
                ['key' => 'open', 'label' => 'Open / in progress', 'value' => $openCount, 'tone' => 'neutral'],
                ['key' => 'assigned_me', 'label' => 'Assigned to me', 'value' => $assignedToMe, 'tone' => 'warning'],
                ['key' => 'urgent', 'label' => 'Urgent', 'value' => $urgentCount, 'tone' => 'danger'],
                ...($canManage ? [['key' => 'sla_at_risk', 'label' => 'SLA at risk', 'value' => $slaAtRisk, 'tone' => 'warning']] : []),
                ['key' => 'resolved_week', 'label' => 'Resolved (7d)', 'value' => $resolvedThisWeek, 'tone' => 'success'],
            ],
            'recent_tickets' => $recent,
            'by_category' => $this->categoryAnalytics($user, $canManage),
            'message' => 'Cross-module issue tracking — raise tickets from any TowerOS module or manually.',
        ];
    }

    /**
     * @return list<array{
     *   category: string|null,
     *   label: string,
     *   open: int,
     *   in_progress: int,
     *   resolved_7d: int,
     *   sla_at_risk: int,
     *   avg_resolve_hours: float|null
     * }>
     */
    private function categoryAnalytics(TenantUser $user, bool $canManage): array
    {
        $userId = (string) $user->id;
        $labelMap = [];
        foreach ($this->categories->resolveOptions() as $option) {
            $labelMap[$option['id']] = $option['label'];
        }

        $scope = static function ($query) use ($canManage, $userId) {
            return $query->when(! $canManage, fn ($q) => $q->where(function ($inner) use ($userId): void {
                $inner->where('requester_id', $userId)->orWhere('assignee_id', $userId);
            }));
        };

        // Active-ticket aggregates (open / in-progress / SLA at risk) grouped in SQL.
        $activeRows = $scope(TicketingTicket::query())
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->selectRaw('category, status, sla_status, COUNT(*) as total')
            ->groupBy('category', 'status', 'sla_status')
            ->get();

        // Resolved-in-7d aggregates (count + avg resolve hours) grouped in SQL.
        $driver = DB::connection('tenant')->getDriverName();
        $avgHoursExpr = $driver === 'sqlite'
            ? '(julianday(resolved_at) - julianday(created_at)) * 24'
            : 'TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60';
        $resolvedRows = $scope(TicketingTicket::query())
            ->where('status', TicketingTicket::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->subDays(7))
            ->whereNotNull('created_at')
            ->whereNotNull('resolved_at')
            ->selectRaw("category, COUNT(*) as resolved_count, AVG({$avgHoursExpr}) as avg_hours")
            ->groupBy('category')
            ->get();

        /** @var array<string, array{category: ?string, open: int, in_progress: int, resolved_7d: int, sla_at_risk: int, avg_resolve_hours: ?float}> $byCategory */
        $byCategory = [];
        $ensure = static function (?string $category) use (&$byCategory): string {
            $key = $category ?? '__uncategorized__';
            if (! isset($byCategory[$key])) {
                $byCategory[$key] = [
                    'category' => $category,
                    'open' => 0,
                    'in_progress' => 0,
                    'resolved_7d' => 0,
                    'sla_at_risk' => 0,
                    'avg_resolve_hours' => null,
                ];
            }

            return $key;
        };

        foreach ($activeRows as $row) {
            $key = $ensure($row->category !== null ? (string) $row->category : null);
            $total = (int) $row->total;
            if ($row->status === TicketingTicket::STATUS_OPEN) {
                $byCategory[$key]['open'] += $total;
            } elseif ($row->status === TicketingTicket::STATUS_IN_PROGRESS) {
                $byCategory[$key]['in_progress'] += $total;
            }
            if (in_array((string) $row->sla_status, ['at_risk', 'breached'], true)) {
                $byCategory[$key]['sla_at_risk'] += $total;
            }
        }

        foreach ($resolvedRows as $row) {
            $key = $ensure($row->category !== null ? (string) $row->category : null);
            $byCategory[$key]['resolved_7d'] = (int) $row->resolved_count;
            $byCategory[$key]['avg_resolve_hours'] = $row->avg_hours !== null
                ? round((float) $row->avg_hours, 1)
                : null;
        }

        $rows = [];
        foreach ($byCategory as $entry) {
            $category = $entry['category'];
            $rows[] = [
                'category' => $category,
                'label' => $category === null
                    ? 'Uncategorized'
                    : ($labelMap[$category] ?? TicketingCategoryCatalog::labelFor($category)),
                'open' => $entry['open'],
                'in_progress' => $entry['in_progress'],
                'resolved_7d' => $entry['resolved_7d'],
                'sla_at_risk' => $entry['sla_at_risk'],
                'avg_resolve_hours' => $entry['avg_resolve_hours'],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $aTotal = $a['open'] + $a['in_progress'] + $a['resolved_7d'];
            $bTotal = $b['open'] + $b['in_progress'] + $b['resolved_7d'];

            return $bTotal <=> $aTotal;
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSummary(TicketingTicket $ticket): array
    {
        return [
            'id' => (string) $ticket->id,
            'ticket_number' => $ticket->displayNumber(),
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'source_module' => $ticket->source_module,
            'requester' => $ticket->requester ? [
                'id' => (string) $ticket->requester->id,
                'name' => $ticket->requester->name,
            ] : null,
            'assignee' => $ticket->assignee ? [
                'id' => (string) $ticket->assignee->id,
                'name' => $ticket->assignee->name,
            ] : null,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
