<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantScopedCache;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TicketingDashboardService
{
    public function __construct(
        private readonly TicketingCategoryCatalog $categories,
        private readonly TicketingTicketService $tickets,
    ) {}

    /**
     * @param  array{
     *   status?: string|null,
     *   priority?: string|null,
     *   category?: string|null,
     *   department?: string|null,
     *   mine?: bool,
     *   assigned_me?: bool,
     * }  $filters
     * @return array<string, mixed>
     */
    public function build(TenantUser $user, array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $tenantId = (string) (tenant('id') ?? 'unknown');
        $filterKey = $normalized === [] ? 'default' : sha1((string) json_encode($normalized));

        return TenantScopedCache::remember(
            "ticketing:dashboard:{$tenantId}:{$user->id}:{$filterKey}",
            30,
            fn (): array => $this->buildUncached($user, $normalized),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $out = [];
        foreach (['status', 'priority', 'category', 'department'] as $key) {
            $value = isset($filters[$key]) ? trim((string) $filters[$key]) : '';
            if ($value !== '') {
                $out[$key] = $value;
            }
        }
        if (! empty($filters['mine'])) {
            $out['mine'] = true;
        }
        if (! empty($filters['assigned_me'])) {
            $out['assigned_me'] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildUncached(TenantUser $user, array $filters): array
    {
        $canManage = $user->can('ticketing:tickets:manage');
        $userId = (string) $user->id;
        $base = fn (): Builder => $this->tickets->filteredQuery($user, $filters);

        $openCount = (clone $base())
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->count();

        $assignedToMe = (clone $base())
            ->where('assignee_id', $userId)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->count();

        $urgentCount = (clone $base())
            ->where('priority', TicketingTicket::PRIORITY_URGENT)
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->count();

        $resolvedThisWeek = (clone $base())
            ->where('status', TicketingTicket::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->subDays(7))
            ->count();

        $slaAtRisk = 0;
        if ($canManage) {
            $slaAtRisk = (clone $base())
                ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
                ->whereIn('sla_status', ['at_risk', 'breached'])
                ->count();
        }

        $recent = (clone $base())
            ->with(['requester:id,name,email', 'assignee:id,name,email'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TicketingTicket $ticket) => $this->ticketSummary($ticket))
            ->all();

        return [
            'kpis' => [
                [
                    'key' => 'open',
                    'label' => 'Open / in progress',
                    'value' => $openCount,
                    'tone' => 'neutral',
                    'href' => '/ticketing/tickets?status=open,in_progress',
                ],
                [
                    'key' => 'assigned_me',
                    'label' => 'Assigned to me',
                    'value' => $assignedToMe,
                    'tone' => 'warning',
                    'href' => '/ticketing/tickets?assigned_me=1',
                ],
                [
                    'key' => 'urgent',
                    'label' => 'Urgent',
                    'value' => $urgentCount,
                    'tone' => 'danger',
                    'href' => '/ticketing/tickets?priority=urgent&status=open,in_progress',
                ],
                ...($canManage ? [[
                    'key' => 'sla_at_risk',
                    'label' => 'SLA at risk',
                    'value' => $slaAtRisk,
                    'tone' => 'warning',
                    'href' => '/ticketing/tickets?sla_status=at_risk,breached&status=open,in_progress',
                ]] : []),
                [
                    'key' => 'resolved_week',
                    'label' => 'Resolved (7d)',
                    'value' => $resolvedThisWeek,
                    'tone' => 'success',
                    'href' => '/ticketing/tickets?status=resolved',
                ],
            ],
            'recent_tickets' => $recent,
            'by_category' => $this->categoryAnalytics($user, $filters),
            'status_breakdown' => $this->statusBreakdown(clone $base()),
            'priority_breakdown' => $this->priorityBreakdown(clone $base()),
            'department_breakdown' => $this->departmentBreakdown(clone $base()),
            'filter_options' => [
                'departments' => $this->departmentOptions($user),
            ],
            'applied_filters' => [
                'status' => $filters['status'] ?? null,
                'priority' => $filters['priority'] ?? null,
                'category' => $filters['category'] ?? null,
                'department' => $filters['department'] ?? null,
                'mine' => (bool) ($filters['mine'] ?? false),
                'assigned_me' => (bool) ($filters['assigned_me'] ?? false),
            ],
            'message' => 'Cross-module issue tracking — raise tickets from any INFRA SUITE module or manually.',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function categoryAnalytics(TenantUser $user, array $filters): array
    {
        $labelMap = [];
        foreach ($this->categories->resolveOptions() as $option) {
            $labelMap[$option['id']] = $option['label'];
        }

        $base = fn (): Builder => $this->tickets->filteredQuery($user, $filters);

        $activeRows = (clone $base())
            ->whereIn('status', [TicketingTicket::STATUS_OPEN, TicketingTicket::STATUS_IN_PROGRESS])
            ->selectRaw('category, status, sla_status, COUNT(*) as total')
            ->groupBy('category', 'status', 'sla_status')
            ->get();

        $driver = DB::connection('tenant')->getDriverName();
        $avgHoursExpr = $driver === 'sqlite'
            ? '(julianday(resolved_at) - julianday(created_at)) * 24'
            : 'TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60';
        $resolvedRows = (clone $base())
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
     * @return list<array{status: string, label: string, count: int}>
     */
    private function statusBreakdown(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        return $rows->map(static fn ($row): array => [
            'status' => (string) $row->status,
            'label' => str_replace('_', ' ', ucfirst((string) $row->status)),
            'count' => (int) $row->total,
        ])->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function priorityBreakdown(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->orderByDesc('total')
            ->get();

        return $rows->map(static fn ($row): array => [
            'key' => (string) $row->priority,
            'label' => ucfirst((string) $row->priority),
            'count' => (int) $row->total,
        ])->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function departmentBreakdown(Builder $query): array
    {
        if (! Schema::connection('tenant')->hasColumn('users', 'department')) {
            return [];
        }

        $rows = (clone $query)
            ->join('users as requesters', 'requesters.id', '=', 'ticketing_tickets.requester_id')
            ->whereNotNull('requesters.department')
            ->where('requesters.department', '!=', '')
            ->selectRaw('requesters.department as department, COUNT(*) as total')
            ->groupBy('requesters.department')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return $rows->map(static fn ($row): array => [
            'key' => (string) $row->department,
            'label' => (string) $row->department,
            'count' => (int) $row->total,
        ])->values()->all();
    }

    /**
     * @return list<string>
     */
    private function departmentOptions(TenantUser $user): array
    {
        if (! Schema::connection('tenant')->hasColumn('users', 'department')) {
            return [];
        }

        return $this->tickets->filteredQuery($user, [])
            ->join('users as requesters', 'requesters.id', '=', 'ticketing_tickets.requester_id')
            ->whereNotNull('requesters.department')
            ->where('requesters.department', '!=', '')
            ->distinct()
            ->orderBy('requesters.department')
            ->pluck('requesters.department')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
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
