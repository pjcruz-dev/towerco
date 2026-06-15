<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalComment;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

final class EApprovalCommentService
{
    public function __construct(
        private readonly EApprovalInAppNotificationService $inApp,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listThread(EApprovalSubmission $submission): array
    {
        $comments = EApprovalComment::query()
            ->with(['user:id,name', 'replies.user:id,name'])
            ->where('submission_id', $submission->id)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->get();

        return $comments->map(static fn (EApprovalComment $c) => [
            'id' => (string) $c->id,
            'message' => $c->message,
            'user_name' => $c->user?->name ?? '—',
            'created_at' => $c->created_at?->toIso8601String(),
            'replies' => $c->replies->map(static fn (EApprovalComment $r) => [
                'id' => (string) $r->id,
                'message' => $r->message,
                'user_name' => $r->user?->name ?? '—',
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
        ])->values()->all();
    }

    public function add(
        EApprovalSubmission $submission,
        string $message,
        TenantUser $actor,
        ?string $parentId = null,
        bool $notifyStakeholders = true,
    ): EApprovalComment {
        $trimmed = trim($message);

        $comment = EApprovalComment::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'user_id' => $actor->id,
            'message' => $trimmed,
            'parent_id' => $parentId,
        ]);

        if ($notifyStakeholders && $trimmed !== '') {
            $this->notifyStakeholders($submission, $actor, $trimmed, $parentId);
        }

        return $comment;
    }

    private function notifyStakeholders(
        EApprovalSubmission $submission,
        TenantUser $actor,
        string $message,
        ?string $parentId,
    ): void {
        $submission->loadMissing('form:id,name');
        $actorId = (string) $actor->id;
        $recipientIds = [];

        $requestorId = (string) $submission->requestor_id;
        if ($requestorId !== '' && $requestorId !== $actorId) {
            $recipientIds[] = $requestorId;
        }

        $pendingApproverIds = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->whereNotNull('approver_id')
            ->pluck('approver_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($pendingApproverIds as $approverId) {
            if ($approverId !== '' && $approverId !== $actorId) {
                $recipientIds[] = $approverId;
            }
        }

        if ($parentId !== null && $parentId !== '') {
            $parentAuthorId = EApprovalComment::query()
                ->where('id', $parentId)
                ->value('user_id');

            if (is_string($parentAuthorId) && $parentAuthorId !== '' && $parentAuthorId !== $actorId) {
                $recipientIds[] = $parentAuthorId;
            }
        }

        $recipientIds = array_values(array_unique($recipientIds));
        if ($recipientIds === []) {
            return;
        }

        $isReply = $parentId !== null && $parentId !== '';
        $documentNo = (string) $submission->document_no;

        $type = $isReply ? 'comment_replied' : 'comment_added';

        foreach ($recipientIds as $userId) {
            $this->inApp->notify(
                $userId,
                $type,
                $submission->id,
                $isReply
                    ? __(':name replied on :doc.', ['name' => $actor->name, 'doc' => $documentNo])
                    : __(':name commented on :doc.', ['name' => $actor->name, 'doc' => $documentNo]),
                submission: $submission,
                actor: $actor,
                bodyPreview: $message,
            );
        }
    }
}
