<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class EApprovalAdminStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $formsPublished = EApprovalForm::query()->where('status', 'published')->count();
        $formsDraft = EApprovalForm::query()->where('status', '!=', 'published')->count();

        $submissionCounts = EApprovalSubmission::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $totalSubmissions = array_sum(array_map(static fn ($v) => (int) $v, $submissionCounts));

        $topForms = DB::connection('tenant')
            ->table('e_approval_submissions')
            ->select('form_id', DB::raw('COUNT(*) as submission_count'))
            ->groupBy('form_id')
            ->orderByDesc('submission_count')
            ->limit(5)
            ->get();

        $formNames = EApprovalForm::query()
            ->whereIn('id', $topForms->pluck('form_id')->filter()->all())
            ->pluck('name', 'id');

        $topFormsRows = $topForms->map(static function ($row) use ($formNames) {
            $formId = (string) $row->form_id;

            return [
                'form_id' => $formId,
                'form_name' => (string) ($formNames[$formId] ?? 'Unknown form'),
                'submission_count' => (int) $row->submission_count,
            ];
        })->values()->all();

        $volume7d = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $next = $day->copy()->endOfDay();
            $count = EApprovalSubmission::query()
                ->whereBetween('created_at', [$day, $next])
                ->count();
            $volume7d[] = [
                'date' => $day->toDateString(),
                'count' => $count,
            ];
        }

        return [
            'forms' => [
                'published' => $formsPublished,
                'draft' => $formsDraft,
                'total' => $formsPublished + $formsDraft,
            ],
            'submissions' => [
                'total' => $totalSubmissions,
                'by_status' => $submissionCounts,
                'open' => EApprovalSubmission::query()
                    ->whereNotIn('status', [
                        EApprovalSubmissionStatus::APPROVED,
                        EApprovalSubmissionStatus::REJECTED,
                        EApprovalSubmissionStatus::CANCELLED,
                    ])
                    ->count(),
            ],
            'top_forms' => $topFormsRows,
            'volume_7d' => $volume7d,
        ];
    }
}
