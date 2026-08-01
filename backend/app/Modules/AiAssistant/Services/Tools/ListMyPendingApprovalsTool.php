<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\EApproval\Services\ApprovalDecisionService;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;

final class ListMyPendingApprovalsTool implements AssistantToolInterface
{
    public function __construct(
        private readonly ApprovalDecisionService $approvals,
    ) {}

    public function name(): string
    {
        return 'list_my_pending_approvals';
    }

    public function description(): string
    {
        return 'List E-Approval items currently awaiting the viewer\'s decision.';
    }

    public function requiredModule(): ?string
    {
        return 'e_approval';
    }

    public function requiredPermissions(): array
    {
        return ['e_approval:approve'];
    }

    public function argumentRules(): array
    {
        return [];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $paginator = $this->approvals->paginate(
            viewer: $viewer,
            page: 1,
            perPage: $maxRows,
            status: EApprovalApprovalStatus::PENDING,
            awaitingMeOnly: true,
        );

        $rows = [];
        foreach ($paginator->items() as $approval) {
            $row = $approval->toListRow();
            $rows[] = [
                'approval_id' => $row['id'] ?? null,
                'approval_status' => $row['approval_status'] ?? $row['status'] ?? null,
                'document_no' => $row['submission']['document_no'] ?? null,
                'form_name' => $row['submission']['form_name'] ?? null,
                'submission_status' => $row['submission']['status'] ?? null,
                'submission_id' => $row['submission']['id'] ?? null,
                'step_order' => $row['step_order'] ?? null,
            ];
        }

        $count = count($rows);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'pending_approvals' => $rows,
                'total' => $paginator->total(),
                'returned' => $count,
            ],
            summary: $count === 0
                ? 'You have no pending approvals awaiting you.'
                : sprintf('You have %d pending approval(s) awaiting you (showing %d).', $paginator->total(), $count),
            moduleKey: 'e_approval',
            relatedRoutes: ['/e-approval/approvals?awaiting_me=1'],
            rowCount: $count,
        );
    }
}
