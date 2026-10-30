<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalComment;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalCommentService
{
    public function __construct(
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
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

        return $comments->map(fn (EApprovalComment $c) => [
            'id' => (string) $c->id,
            'message' => $c->message,
            'images' => $this->imagePayload($c),
            'user_name' => $c->user?->name ?? '—',
            'created_at' => $c->created_at?->toIso8601String(),
            'attachment_id' => $c->attachment_id !== null ? (string) $c->attachment_id : null,
            'pin_page' => $c->pin_page !== null ? (int) $c->pin_page : null,
            'pin_x' => $c->pin_x !== null ? (float) $c->pin_x : null,
            'pin_y' => $c->pin_y !== null ? (float) $c->pin_y : null,
            'pin_w' => $c->pin_w !== null ? (float) $c->pin_w : null,
            'pin_h' => $c->pin_h !== null ? (float) $c->pin_h : null,
            'replies' => $c->replies->map(fn (EApprovalComment $r) => [
                'id' => (string) $r->id,
                'message' => $r->message,
                'images' => $this->imagePayload($r),
                'user_name' => $r->user?->name ?? '—',
                'created_at' => $r->created_at?->toIso8601String(),
                'attachment_id' => $r->attachment_id !== null ? (string) $r->attachment_id : null,
            ])->values()->all(),
        ])->values()->all();
    }

    public function add(
        EApprovalSubmission $submission,
        string $message,
        TenantUser $actor,
        ?string $parentId = null,
        bool $notifyStakeholders = true,
        ?string $attachmentId = null,
        ?int $pinPage = null,
        ?float $pinX = null,
        ?float $pinY = null,
        ?float $pinW = null,
        ?float $pinH = null,
        array $mentionUserIds = [],
        array $imageAttachmentIds = [],
    ): EApprovalComment {
        $trimmed = trim($message);
        $attachmentId = $this->attachmentOnSubmission($submission, $attachmentId);
        [$pinPage, $pinX, $pinY, $pinW, $pinH] = $this->pinOnAttachment(
            $attachmentId,
            $pinPage,
            $pinX,
            $pinY,
            $pinW,
            $pinH,
            $parentId,
        );

        if ($parentId !== null && $parentId !== '' && $attachmentId === null) {
            $parentAttachment = EApprovalComment::query()
                ->where('submission_id', $submission->id)
                ->where('id', $parentId)
                ->value('attachment_id');
            $attachmentId = is_string($parentAttachment) && $parentAttachment !== '' ? $parentAttachment : null;
        }

        $comment = EApprovalComment::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'user_id' => $actor->id,
            'message' => $trimmed,
            'parent_id' => $parentId,
            'attachment_id' => $attachmentId,
            'pin_page' => $pinPage,
            'pin_x' => $pinX,
            'pin_y' => $pinY,
            'pin_w' => $pinW,
            'pin_h' => $pinH,
            'image_attachment_ids' => $imageAttachmentIds === [] ? null : array_values($imageAttachmentIds),
        ]);

        $notice = $trimmed !== '' ? $trimmed : ($imageAttachmentIds !== [] ? __('Shared an image.') : '');
        if ($notifyStakeholders && $notice !== '') {
            $this->notifyStakeholders($submission, $actor, $notice, $parentId, $mentionUserIds);
        }

        return $comment;
    }

    /**
     * @return list<array{id: string, file_name: string}>
     */
    private function imagePayload(EApprovalComment $comment): array
    {
        $ids = is_array($comment->image_attachment_ids) ? $comment->image_attachment_ids : [];
        $ids = array_values(array_filter($ids, static fn ($id) => is_string($id) && $id !== ''));
        if ($ids === []) {
            return [];
        }

        return EApprovalAttachment::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(static fn (EApprovalAttachment $file): array => [
                'id' => (string) $file->id,
                'file_name' => (string) $file->file_name,
            ])
            ->values()
            ->all();
    }

    private function attachmentOnSubmission(EApprovalSubmission $submission, ?string $attachmentId): ?string
    {
        $attachmentId = trim((string) $attachmentId);
        if ($attachmentId === '') {
            return null;
        }

        $owns = EApprovalAttachment::query()
            ->where('id', $attachmentId)
            ->where('submission_id', $submission->id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'attachment_id' => [__('That file is not on this request.')],
            ]);
        }

        return $attachmentId;
    }

    /**
     * @return array{0: int|null, 1: float|null, 2: float|null, 3: float|null, 4: float|null}
     */
    private function pinOnAttachment(
        ?string $attachmentId,
        ?int $pinPage,
        ?float $pinX,
        ?float $pinY,
        ?float $pinW,
        ?float $pinH,
        ?string $parentId,
    ): array {
        $placed = $pinPage !== null || $pinX !== null || $pinY !== null || $pinW !== null || $pinH !== null;
        if (! $placed || ($parentId !== null && $parentId !== '')) {
            return [null, null, null, null, null];
        }

        if ($attachmentId === null || $pinPage === null || $pinX === null || $pinY === null) {
            throw ValidationException::withMessages([
                'pin_page' => [__('A comment on the page needs the file, the page, and the spot.')],
            ]);
        }

        if ($pinPage < 1 || $pinX < 0 || $pinX > 1 || $pinY < 0 || $pinY > 1) {
            throw ValidationException::withMessages([
                'pin_page' => [__('That spot is outside the page.')],
            ]);
        }

        if (($pinW === null) !== ($pinH === null) || ($pinW !== null && ($pinW <= 0 || $pinW > 1 || $pinH <= 0 || $pinH > 1))) {
            throw ValidationException::withMessages([
                'pin_w' => [__('That highlight does not fit on the page.')],
            ]);
        }

        return [
            $pinPage,
            round($pinX, 6),
            round($pinY, 6),
            $pinW !== null ? round($pinW, 6) : null,
            $pinH !== null ? round($pinH, 6) : null,
        ];
    }

    /**
     * @param  list<string>  $mentionUserIds
     */
    private function notifyStakeholders(
        EApprovalSubmission $submission,
        TenantUser $actor,
        string $message,
        ?string $parentId,
        array $mentionUserIds = [],
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
            ->whereIn('status', [EApprovalApprovalStatus::PENDING, EApprovalApprovalStatus::ACKNOWLEDGED])
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
        $mentionedIds = $this->mentionedIds($submission, $actorId, $mentionUserIds);
        if ($recipientIds === [] && $mentionedIds === []) {
            return;
        }

        $isReply = $parentId !== null && $parentId !== '';
        $documentNo = (string) $submission->document_no;

        $type = $isReply ? 'comment_replied' : 'comment_added';
        $preview = mb_strlen($message) > 300 ? mb_substr($message, 0, 297).'...' : $message;

        foreach ($recipientIds as $userId) {
            if (in_array($userId, $mentionedIds, true)) {
                continue;
            }
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
            $this->mail->dispatchComment($submission, $userId, (string) $actor->name, $preview, $isReply);
        }

        foreach ($mentionedIds as $userId) {
            $this->inApp->notify(
                $userId,
                'comment_mention',
                $submission->id,
                __(':name mentioned you on :doc.', ['name' => $actor->name, 'doc' => $documentNo]),
                submission: $submission,
                actor: $actor,
                bodyPreview: $message,
            );
            $this->mail->dispatchCommentMention($submission, $userId, (string) $actor->name, $preview);
        }
    }

    /**
     * @param  list<string>  $mentionUserIds
     * @return list<string>
     */
    private function mentionedIds(EApprovalSubmission $submission, string $actorId, array $mentionUserIds): array
    {
        $allowed = [(string) $submission->requestor_id];
        $approverIds = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->whereNotNull('approver_id')
            ->pluck('approver_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $allowed = array_values(array_unique([...$allowed, ...$approverIds]));

        $mentioned = [];
        foreach ($mentionUserIds as $mentionId) {
            $mentionId = trim((string) $mentionId);
            if ($mentionId !== '' && $mentionId !== $actorId && in_array($mentionId, $allowed, true)) {
                $mentioned[] = $mentionId;
            }
        }

        return array_values(array_unique($mentioned));
    }
}
