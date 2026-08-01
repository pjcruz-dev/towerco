<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;

final class ListMyEApprovalSubmissionsTool implements AssistantToolInterface
{
    public function name(): string
    {
        return 'list_my_eapproval_submissions';
    }

    public function description(): string
    {
        return 'List the viewer\'s own E-Approval submissions by status.';
    }

    public function requiredModule(): ?string
    {
        return 'e_approval';
    }

    public function requiredPermissions(): array
    {
        return ['e_approval:submissions:view'];
    }

    public function argumentRules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:open,pending,draft,approved,rejected,cancelled,returned,awaiting_dcf,all'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $status = (string) ($args['status'] ?? 'open');
        $query = EApprovalSubmission::query()
            ->with(['form:id,name'])
            ->where('requestor_id', $viewer->id)
            ->orderByDesc('created_at');

        if ($status === 'open') {
            $query->whereIn('status', EApprovalSubmissionStatus::open());
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $submissions = $query->limit($maxRows)->get();

        $rows = $submissions->map(static function (EApprovalSubmission $submission): array {
            return [
                'submission_id' => (string) $submission->id,
                'document_no' => $submission->document_no,
                'form_name' => $submission->form?->name,
                'status' => $submission->status,
                'current_step' => $submission->current_step,
                'created_at' => $submission->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $label = $status === 'open' ? 'open or pending' : str_replace('_', ' ', $status);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'submissions' => $rows,
                'status' => $status,
                'total' => $total,
                'returned' => count($rows),
            ],
            summary: $total === 0
                ? sprintf('You have no %s E-Approval submissions.', $label)
                : sprintf('You have %d %s E-Approval submission(s) (showing %d).', $total, $label, count($rows)),
            moduleKey: 'e_approval',
            relatedRoutes: ['/e-approval/submissions?mine=1&status='.($status === 'open' ? 'pending' : $status)],
            rowCount: count($rows),
        );
    }
}
