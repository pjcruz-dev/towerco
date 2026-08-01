<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;

final class GetEApprovalSubmissionByDocumentNoTool implements AssistantToolInterface
{
    public function name(): string
    {
        return 'get_eapproval_submission_by_document_no';
    }

    public function description(): string
    {
        return 'Look up an E-Approval submission by document number (e.g. GEN-F-00042).';
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
            'document_no' => ['required', 'string', 'min:2', 'max:64'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $documentNo = trim((string) $args['document_no']);
        if ($documentNo === '') {
            return new ToolResult(
                tool: $this->name(),
                ok: false,
                data: [],
                summary: 'Document number is required.',
                moduleKey: 'e_approval',
                relatedRoutes: ['/e-approval/submissions'],
                rowCount: 0,
                error: 'missing document_no',
            );
        }

        $canViewAll = $viewer->can('e_approval:forms:manage');

        $exactQuery = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name'])
            ->where('document_no', $documentNo);
        $this->applyVisibility($exactQuery, $viewer, $canViewAll);
        $exact = $exactQuery->first();

        if ($exact instanceof EApprovalSubmission) {
            return $this->successResult($exact, 'exact');
        }

        $needle = '%'.addcslashes($documentNo, '%_\\').'%';
        $searchQuery = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name'])
            ->where('document_no', 'like', $needle)
            ->orderByDesc('created_at');
        $this->applyVisibility($searchQuery, $viewer, $canViewAll);

        $candidates = $searchQuery->limit(max(1, min($maxRows, 5)))->get();
        if ($candidates->isEmpty()) {
            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['submission' => null, 'candidates' => []],
                summary: sprintf('No E-Approval submission found for document number "%s" (or you do not have access).', $documentNo),
                moduleKey: 'e_approval',
                relatedRoutes: ['/e-approval/submissions'],
                rowCount: 0,
            );
        }

        /** @var EApprovalSubmission $best */
        $best = $candidates->first();

        return $this->successResult($best, 'search', $candidates->all());
    }

    /**
     * @param  Builder<EApprovalSubmission>  $query
     */
    private function applyVisibility(Builder $query, TenantUser $viewer, bool $canViewAll): void
    {
        if ($canViewAll) {
            return;
        }

        $query->where(static function (Builder $inner) use ($viewer): void {
            $inner->where('requestor_id', $viewer->id)
                ->orWhereIn('id', EApprovalRequestApproval::query()
                    ->where('approver_id', $viewer->id)
                    ->select('submission_id'));
        });
    }

    /**
     * @param  list<EApprovalSubmission>  $candidates
     */
    private function successResult(EApprovalSubmission $submission, string $match, array $candidates = []): ToolResult
    {
        $row = $this->summarize($submission);
        $extra = [];
        if ($candidates !== []) {
            $extra = array_map(fn (EApprovalSubmission $item): array => $this->summarize($item), $candidates);
        }

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'submission' => $row,
                'match' => $match,
                'candidates' => $extra,
            ],
            summary: sprintf(
                'Submission %s — %s. Status: %s%s.',
                $row['document_no'],
                $row['form_name'] ?? 'Unknown form',
                $row['status'] ?? 'unknown',
                isset($row['requestor']) ? '. Requestor: '.$row['requestor'] : '',
            ),
            moduleKey: 'e_approval',
            relatedRoutes: [$row['href']],
            rowCount: max(1, count($extra)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(EApprovalSubmission $submission): array
    {
        return [
            'id' => (string) $submission->id,
            'document_no' => (string) $submission->document_no,
            'form_name' => $submission->form?->name,
            'status' => $submission->status,
            'current_step' => $submission->current_step,
            'requestor' => $submission->requestor?->name,
            'href' => '/e-approval/submissions/'.(string) $submission->id,
        ];
    }
}
