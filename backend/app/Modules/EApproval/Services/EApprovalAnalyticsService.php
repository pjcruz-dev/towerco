<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionFieldFilter;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class EApprovalAnalyticsService
{
    /** @var array{form_id: ?string, subsidiary: ?string, department: ?string} */
    private array $activeFilters = [
        'form_id' => null,
        'subsidiary' => null,
        'department' => null,
    ];

    public function __construct(
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalSlaClock $slaClock,
    ) {}

    /**
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     filters: array{form_id: string|null, subsidiary: string|null, department: string|null},
     *     kpis: list<array{key: string, label: string, value: string, change: string|null, tone: string, href: string|null}>,
     *     submissions_over_time: list<array{key: string, label: string, value: int, href: string}>,
     *     by_status: list<array{key: string, label: string, value: int, href: string}>,
     *     top_forms: list<array{key: string, label: string, value: int, href: string}>,
     *     cycle_times: list<array{key: string, label: string, value: string, unit: string}>,
     *     bottlenecks: list<array{key: string, label: string, value: int, avg_age_hours: float, href: string|null}>,
     *     approver_load: list<array{key: string, label: string, value: int, href: string|null}>,
     *     aging: list<array{key: string, label: string, value: int, href: string}>,
     *     rejection_reasons: list<array{key: string, label: string, value: int}>
     * }
     */
    public function build(
        ?string $from = null,
        ?string $to = null,
        ?string $formId = null,
        ?string $subsidiary = null,
        ?string $department = null,
    ): array {
        $this->activeFilters = [
            'form_id' => $this->nullableTrim($formId),
            'subsidiary' => $this->nullableTrim($subsidiary),
            'department' => $this->nullableTrim($department),
        ];

        $tenantId = (string) (tenant('id') ?? 'unknown');
        $key = sprintf(
            'e_approval:analytics:%s:%s:%s:%s:%s:%s',
            $tenantId,
            $from ?? 'def',
            $to ?? 'def',
            $this->activeFilters['form_id'] ?? 'all',
            $this->activeFilters['subsidiary'] ?? 'all',
            $this->activeFilters['department'] ?? 'all',
        );

        return TenantScopedCache::remember($key, 60, fn (): array => $this->buildUncached($from, $to));
    }

    /**
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     filters: array{form_id: string|null, subsidiary: string|null, department: string|null},
     *     kpis: list<array{key: string, label: string, value: string, change: string|null, tone: string, href: string|null}>,
     *     submissions_over_time: list<array{key: string, label: string, value: int, href: string}>,
     *     by_status: list<array{key: string, label: string, value: int, href: string}>,
     *     top_forms: list<array{key: string, label: string, value: int, href: string}>,
     *     cycle_times: list<array{key: string, label: string, value: string, unit: string}>,
     *     bottlenecks: list<array{key: string, label: string, value: int, avg_age_hours: float, href: string|null}>,
     *     approver_load: list<array{key: string, label: string, value: int, href: string|null}>,
     *     aging: list<array{key: string, label: string, value: int, href: string}>,
     *     rejection_reasons: list<array{key: string, label: string, value: int}>
     * }
     */
    private function buildUncached(?string $from, ?string $to): array
    {
        $toDate = $to !== null && $to !== ''
            ? CarbonImmutable::parse($to)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $fromDate = $from !== null && $from !== ''
            ? CarbonImmutable::parse($from)->startOfDay()
            : $toDate->subDays(29)->startOfDay();

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate->startOfDay(), $fromDate->endOfDay()];
        }

        $days = max(1, $fromDate->diffInDays($toDate) + 1);
        $fromStr = $fromDate->toDateString();
        $toStr = $toDate->toDateString();

        $reminderMinutes = $this->settings->getInt(EApprovalSettingsService::SLA_REMINDER_MINUTES, 48 * 60);
        $escalationMinutes = $this->settings->getInt(EApprovalSettingsService::SLA_ESCALATION_MINUTES, 72 * 60);

        $volume = $this->submissionsOverTime($fromDate, $toDate);
        $byStatus = $this->byStatus($fromDate, $toDate);
        $topForms = $this->topForms($fromDate, $toDate);
        $cycle = $this->cycleTimes($fromDate, $toDate);
        $bottlenecks = $this->bottlenecks();
        $approverLoad = $this->approverLoad();
        $aging = $this->agingBuckets($reminderMinutes, $escalationMinutes);
        $rejections = $this->rejectionReasons($fromDate, $toDate);

        $submitted = (int) $this->submissionsQuery()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();
        $approved = (int) $this->submissionsQuery()
            ->where('status', EApprovalSubmissionStatus::APPROVED)
            ->whereBetween('updated_at', [$fromDate, $toDate])
            ->count();
        $pendingApprovals = (int) $this->approvalsQuery()
            ->where('status', 'pending')
            ->count();
        $reminderCutoff = $this->slaClock->thresholdBefore(now(), $reminderMinutes);
        $staleApprovals = (int) $this->approvalsQuery()
            ->where('status', 'pending')
            ->where('created_at', '<=', $reminderCutoff)
            ->count();
        $escalated = (int) $this->approvalsQuery()
            ->where('status', 'pending')
            ->whereNotNull('escalated_at')
            ->count();

        $slaUnit = $this->slaClock->usesWorkingDays() ? 'wd-h' : 'h';
        $reminderHours = (int) round($reminderMinutes / 60);
        $escalationHours = (int) round($escalationMinutes / 60);

        return [
            'period' => [
                'from' => $fromStr,
                'to' => $toStr,
                'days' => $days,
            ],
            'filters' => $this->activeFilters,
            'kpis' => [
                [
                    'key' => 'submissions_period',
                    'label' => 'Submissions',
                    'value' => (string) $submitted,
                    'change' => $days.' day window',
                    'tone' => 'neutral',
                    'href' => $this->submissionsHref(['from' => $fromStr, 'to' => $toStr]),
                ],
                [
                    'key' => 'approved_period',
                    'label' => 'Approved',
                    'value' => (string) $approved,
                    'change' => 'Updated in window',
                    'tone' => 'success',
                    'href' => $this->submissionsHref([
                        'status' => 'approved',
                        'from' => $fromStr,
                        'to' => $toStr,
                    ]),
                ],
                [
                    'key' => 'pending_approvals',
                    'label' => 'Pending approvals',
                    'value' => (string) $pendingApprovals,
                    'change' => null,
                    'tone' => 'warning',
                    'href' => '/e-approval/approvals?awaiting_me=1',
                ],
                [
                    'key' => 'sla_at_risk',
                    'label' => 'SLA at risk',
                    'value' => (string) $staleApprovals,
                    'change' => '>'.$reminderHours.$slaUnit.' pending',
                    'tone' => $staleApprovals > 0 ? 'warning' : 'neutral',
                    'href' => '/e-approval/approvals?awaiting_me=1',
                ],
                [
                    'key' => 'escalated',
                    'label' => 'Escalated',
                    'value' => (string) $escalated,
                    'change' => '>'.$escalationHours.$slaUnit,
                    'tone' => $escalated > 0 ? 'danger' : 'neutral',
                    'href' => '/e-approval/approvals?awaiting_me=1',
                ],
                [
                    'key' => 'avg_cycle_hours',
                    'label' => 'Avg cycle time',
                    'value' => $cycle[0]['value'] ?? '—',
                    'change' => $cycle[0]['unit'] ?? 'hours',
                    'tone' => 'neutral',
                    'href' => $this->submissionsHref([
                        'status' => 'approved',
                        'from' => $fromStr,
                        'to' => $toStr,
                    ]),
                ],
            ],
            'submissions_over_time' => $volume,
            'by_status' => $byStatus,
            'top_forms' => $topForms,
            'cycle_times' => $cycle,
            'bottlenecks' => $bottlenecks,
            'approver_load' => $approverLoad,
            'aging' => $aging,
            'rejection_reasons' => $rejections,
        ];
    }

    /**
     * @return Builder<\App\Modules\EApproval\Models\EApprovalSubmission>
     */
    private function submissionsQuery(): Builder
    {
        $query = EApprovalSubmission::query();
        $this->applySubmissionFilters($query);

        return $query;
    }

    /**
     * @return Builder<\App\Modules\EApproval\Models\EApprovalRequestApproval>
     */
    private function approvalsQuery(): Builder
    {
        $query = EApprovalRequestApproval::query();
        if ($this->hasActiveFilters()) {
            $query->whereHas('submission', function (Builder $submission): void {
                $this->applySubmissionFilters($submission);
            });
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Modules\EApproval\Models\EApprovalSubmission>  $query
     */
    private function applySubmissionFilters(Builder $query): void
    {
        if ($this->activeFilters['form_id'] !== null) {
            $query->where('form_id', $this->activeFilters['form_id']);
        }

        $formIds = $this->activeFilters['form_id'] !== null
            ? [$this->activeFilters['form_id']]
            : [];

        EApprovalSubmissionFieldFilter::applyWorkspaceColumnFilters($query, $formIds, [
            'subsidiary' => $this->activeFilters['subsidiary'],
            'department' => $this->activeFilters['department'],
        ]);
    }

    private function hasActiveFilters(): bool
    {
        return $this->activeFilters['form_id'] !== null
            || $this->activeFilters['subsidiary'] !== null
            || $this->activeFilters['department'] !== null;
    }

    /**
     * @param  array<string, string|null>  $extra
     */
    private function submissionsHref(array $extra = []): string
    {
        $params = array_filter([
            ...$extra,
            'form_id' => $this->activeFilters['form_id'],
            'subsidiary' => $this->activeFilters['subsidiary'],
            'department' => $this->activeFilters['department'],
        ], static fn ($value): bool => $value !== null && $value !== '');

        $query = http_build_query($params);

        return '/e-approval/submissions'.($query !== '' ? '?'.$query : '');
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string}>
     */
    private function submissionsOverTime(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $useWeeks = $days > 45;
        $series = [];

        if ($useWeeks) {
            $cursor = $from->startOfWeek();
            while ($cursor->lessThanOrEqualTo($to)) {
                $weekEnd = $cursor->endOfWeek();
                $rangeStart = $cursor->greaterThan($from) ? $cursor : $from;
                $rangeEnd = $weekEnd->lessThan($to) ? $weekEnd : $to;
                $count = $this->submissionsQuery()
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->count();
                $key = $cursor->toDateString();
                $series[] = [
                    'key' => $key,
                    'label' => $cursor->format('M j'),
                    'value' => $count,
                    'href' => $this->submissionsHref([
                        'from' => $rangeStart->toDateString(),
                        'to' => $rangeEnd->toDateString(),
                    ]),
                ];
                $cursor = $cursor->addWeek()->startOfWeek();
            }

            return $series;
        }

        $countsQuery = $this->submissionsQuery()
            ->selectRaw('DATE(created_at) as day_key, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('day_key');
        $counts = $countsQuery->pluck('total', 'day_key');

        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->startOfDay();
            if ($day->greaterThan($to)) {
                break;
            }
            $dateKey = $day->toDateString();
            $series[] = [
                'key' => $dateKey,
                'label' => $day->format('M j'),
                'value' => (int) ($counts[$dateKey] ?? 0),
                'href' => $this->submissionsHref(['from' => $dateKey, 'to' => $dateKey]),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string}>
     */
    private function byStatus(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = $this->submissionsQuery()
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $order = [
            EApprovalSubmissionStatus::PENDING,
            EApprovalSubmissionStatus::RETURNED,
            EApprovalSubmissionStatus::APPROVED,
            EApprovalSubmissionStatus::REJECTED,
            EApprovalSubmissionStatus::CANCELLED,
            EApprovalSubmissionStatus::DRAFT,
        ];

        $rows = [];
        foreach ($order as $status) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value === 0) {
                continue;
            }
            $rows[] = [
                'key' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'value' => $value,
                'href' => $this->submissionsHref([
                    'status' => $status,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ]),
            ];
        }

        foreach ($counts as $status => $total) {
            if (in_array((string) $status, $order, true)) {
                continue;
            }
            $rows[] = [
                'key' => (string) $status,
                'label' => ucfirst(str_replace('_', ' ', (string) $status)),
                'value' => (int) $total,
                'href' => $this->submissionsHref([
                    'status' => (string) $status,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string}>
     */
    private function topForms(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $top = $this->submissionsQuery()
            ->selectRaw('form_id, COUNT(*) as submission_count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('form_id')
            ->orderByDesc('submission_count')
            ->limit(8)
            ->get();

        $names = EApprovalForm::query()
            ->whereIn('id', $top->pluck('form_id')->filter()->all())
            ->pluck('name', 'id');

        return $top->map(function ($row) use ($names, $from, $to): array {
            $formId = (string) $row->form_id;

            return [
                'key' => $formId,
                'label' => (string) ($names[$formId] ?? 'Unknown form'),
                'value' => (int) $row->submission_count,
                'href' => $this->submissionsHref([
                    'form_id' => $formId,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ]),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, value: string, unit: string}>
     */
    private function cycleTimes(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $driver = DB::connection('tenant')->getDriverName();

        $submissionAvg = $this->avgHours(
            $this->submissionsQuery()
                ->where('status', EApprovalSubmissionStatus::APPROVED)
                ->whereBetween('updated_at', [$from, $to])
                ->whereNotNull('created_at')
                ->whereNotNull('updated_at'),
            'created_at',
            'updated_at',
            $driver,
        );

        $stepAvg = $this->avgHours(
            $this->approvalsQuery()
                ->where('status', 'approved')
                ->whereNotNull('acted_at')
                ->whereBetween('acted_at', [$from, $to]),
            'created_at',
            'acted_at',
            $driver,
        );

        return [
            [
                'key' => 'submission_cycle',
                'label' => 'Submission create → approved',
                'value' => $this->formatHours($submissionAvg),
                'unit' => 'hours avg',
            ],
            [
                'key' => 'step_cycle',
                'label' => 'Approval step assigned → acted',
                'value' => $this->formatHours($stepAvg),
                'unit' => 'hours avg',
            ],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function avgHours($query, string $startCol, string $endCol, string $driver): ?float
    {
        if ($driver === 'sqlite') {
            $row = $query->selectRaw("AVG((julianday({$endCol}) - julianday({$startCol})) * 24) as avg_hours")->first();
        } else {
            $row = $query->selectRaw("AVG(TIMESTAMPDIFF(HOUR, {$startCol}, {$endCol})) as avg_hours")->first();
        }

        $value = $row?->avg_hours ?? null;

        return $value === null ? null : (float) $value;
    }

    private function formatHours(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }

        if ($hours >= 48) {
            return number_format($hours / 24, 1).'d';
        }

        return number_format($hours, 1).'h';
    }

    /**
     * @return list<array{key: string, label: string, value: int, avg_age_hours: float, href: string|null}>
     */
    private function bottlenecks(): array
    {
        $driver = DB::connection('tenant')->getDriverName();
        $ageExpr = $driver === 'sqlite'
            ? '(julianday(\'now\') - julianday(a.created_at)) * 24'
            : 'TIMESTAMPDIFF(HOUR, a.created_at, NOW())';

        $query = DB::connection('tenant')
            ->table('e_approval_request_approvals as a')
            ->leftJoin('e_approval_workflow_steps as s', 's.id', '=', 'a.step_id')
            ->where('a.status', 'pending');

        if ($this->hasActiveFilters()) {
            $query->whereIn('a.submission_id', $this->submissionsQuery()->select('id'));
        }

        $rows = $query
            ->selectRaw("COALESCE(s.step_order, 0) as step_order, COUNT(*) as pending_count, AVG({$ageExpr}) as avg_age_hours")
            ->groupBy(DB::raw('COALESCE(s.step_order, 0)'))
            ->orderByDesc('pending_count')
            ->limit(8)
            ->get();

        return $rows->map(static function ($row): array {
            $step = (int) $row->step_order;

            return [
                'key' => 'step-'.$step,
                'label' => $step > 0 ? 'Step '.$step : 'Unmapped step',
                'value' => (int) $row->pending_count,
                'avg_age_hours' => round((float) ($row->avg_age_hours ?? 0), 1),
                'href' => '/e-approval/approvals?awaiting_me=1',
            ];
        })->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string|null}>
     */
    private function approverLoad(): array
    {
        $query = DB::connection('tenant')
            ->table('e_approval_request_approvals')
            ->where('status', 'pending');

        if ($this->hasActiveFilters()) {
            $query->whereIn('submission_id', $this->submissionsQuery()->select('id'));
        }

        $rows = $query
            ->select('approver_id', DB::raw('COUNT(*) as pending_count'))
            ->groupBy('approver_id')
            ->orderByDesc('pending_count')
            ->limit(8)
            ->get();

        $names = TenantUser::query()
            ->whereIn('id', $rows->pluck('approver_id')->filter()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy(static fn (TenantUser $u): string => (string) $u->id);

        return $rows->map(static function ($row) use ($names): array {
            $id = (string) $row->approver_id;
            /** @var TenantUser|null $user */
            $user = $names->get($id);
            $label = $user?->name ?: ($user?->email ?: 'Unknown approver');

            return [
                'key' => $id,
                'label' => $label,
                'value' => (int) $row->pending_count,
                'href' => '/e-approval/approvals?awaiting_me=1',
            ];
        })->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, value: int, href: string}>
     */
    private function agingBuckets(int $reminderMinutes, int $escalationMinutes): array
    {
        $now = Carbon::now();
        $dayCutoff = $this->slaClock->thresholdBefore($now, 24 * 60);
        $threeDayCutoff = $this->slaClock->thresholdBefore($now, 3 * 24 * 60);
        $reminderCutoff = $this->slaClock->thresholdBefore($now, $reminderMinutes);
        $workingLabel = $this->slaClock->usesWorkingDays() ? ' wd' : '';

        $exclusive = $reminderMinutes <= 3 * 24 * 60
            ? [
                [
                    'key' => 'under_24h',
                    'label' => 'Under 24h'.$workingLabel,
                    'gte' => $dayCutoff,
                    'lt' => null,
                ],
                [
                    'key' => '1_to_reminder',
                    'label' => '1d–SLA'.$workingLabel,
                    'gte' => $reminderCutoff,
                    'lt' => $dayCutoff,
                ],
                [
                    'key' => 'beyond_sla',
                    'label' => 'Beyond SLA',
                    'gte' => null,
                    'lt' => $reminderCutoff,
                ],
            ]
            : [
                [
                    'key' => 'under_24h',
                    'label' => 'Under 24h'.$workingLabel,
                    'gte' => $dayCutoff,
                    'lt' => null,
                ],
                [
                    'key' => '1_to_3d',
                    'label' => '1–3 days'.$workingLabel,
                    'gte' => $threeDayCutoff,
                    'lt' => $dayCutoff,
                ],
                [
                    'key' => '3_to_sla',
                    'label' => '3d–SLA'.$workingLabel,
                    'gte' => $reminderCutoff,
                    'lt' => $threeDayCutoff,
                ],
                [
                    'key' => 'beyond_sla',
                    'label' => 'Beyond SLA',
                    'gte' => null,
                    'lt' => $reminderCutoff,
                ],
            ];

        unset($escalationMinutes);

        $result = [];
        foreach ($exclusive as $bucket) {
            $q = $this->approvalsQuery()->where('status', 'pending');
            if ($bucket['gte'] !== null) {
                $q->where('created_at', '>=', $bucket['gte']);
            }
            if ($bucket['lt'] !== null) {
                $q->where('created_at', '<', $bucket['lt']);
            }
            $result[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'value' => $q->count(),
                'href' => '/e-approval/approvals?awaiting_me=1',
            ];
        }

        return $result;
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    private function rejectionReasons(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->approvalsQuery()
            ->selectRaw('remarks, COUNT(*) as total')
            ->where('status', 'rejected')
            ->whereNotNull('remarks')
            ->where('remarks', '!=', '')
            ->whereBetween('acted_at', [$from, $to])
            ->groupBy('remarks')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return $rows->map(static function ($row): array {
            $label = trim((string) $row->remarks);
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 77).'…';
            }

            return [
                'key' => md5((string) $row->remarks),
                'label' => $label !== '' ? $label : 'Unspecified',
                'value' => (int) $row->total,
            ];
        })->values()->all();
    }
}
