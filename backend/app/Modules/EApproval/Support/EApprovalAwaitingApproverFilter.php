<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class EApprovalAwaitingApproverFilter
{
    /**
     * Submissions still pending a decision from this approver.
     *
     * @param  Builder<Model>  $query
     */
    public static function apply(Builder $query, string $approverId): void
    {
        $approverId = trim($approverId);
        if ($approverId === '') {
            return;
        }

        $query->where('status', EApprovalSubmissionStatus::PENDING)
            ->whereIn('id', EApprovalRequestApproval::query()
                ->where('approver_id', $approverId)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->select('submission_id'));
    }
}
