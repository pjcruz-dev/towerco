<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalCommentService;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalFormWorkflowOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EApprovalSubmissionCommentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalCommentService $comments,
        EApprovalSubmissionService $submissions,
        EApprovalFileStorageService $files,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:5000', 'required_without:images'],
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['file', 'image', 'max:5120'],
            'parent_id' => ['nullable', 'uuid'],
            'attachment_id' => ['nullable', 'uuid'],
            'pin_page' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'pin_x' => ['nullable', 'numeric', 'between:0,1'],
            'pin_y' => ['nullable', 'numeric', 'between:0,1'],
            'pin_w' => ['nullable', 'numeric', 'between:0,1'],
            'pin_h' => ['nullable', 'numeric', 'between:0,1'],
            'mention_user_ids' => ['nullable', 'array', 'max:20'],
            'mention_user_ids.*' => ['uuid'],
            'field_name' => ['nullable', 'string', 'max:64'],
        ]);

        $fieldName = isset($data['field_name']) ? trim((string) $data['field_name']) : '';
        if ($fieldName !== '') {
            $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : null;
            if (! EApprovalFormWorkflowOptions::requestCommentsEnabled($metadata)) {
                throw ValidationException::withMessages([
                    'field_name' => [__('Comments on the request are off for this form.')],
                ]);
            }
            $submission->loadMissing('form.fields');
            $known = $submission->form?->fields
                ?->contains(static fn ($field): bool => (string) ($field->name ?? '') === $fieldName) === true;
            if (! $known) {
                $raw = $submission->schema_snapshot_json;
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                $snapshot = is_array($decoded) && is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
                foreach ($snapshot as $field) {
                    if (is_array($field) && (string) ($field['name'] ?? '') === $fieldName) {
                        $known = true;
                        break;
                    }
                }
            }
            if (! $known) {
                throw ValidationException::withMessages([
                    'field_name' => [__('That field is not on this request.')],
                ]);
            }
        }

        $imageIds = [];
        $uploads = $request->file('images', []);
        foreach (is_array($uploads) ? $uploads : [$uploads] as $image) {
            if ($image === null) {
                continue;
            }
            $stored = $files->store($submission, $image, null, ['comment_image' => true]);
            $imageIds[] = (string) $stored->id;
        }

        $comment = $comments->add(
            $submission,
            (string) ($data['message'] ?? ''),
            $request->user(),
            $data['parent_id'] ?? null,
            true,
            $data['attachment_id'] ?? null,
            isset($data['pin_page']) ? (int) $data['pin_page'] : null,
            isset($data['pin_x']) ? (float) $data['pin_x'] : null,
            isset($data['pin_y']) ? (float) $data['pin_y'] : null,
            isset($data['pin_w']) ? (float) $data['pin_w'] : null,
            isset($data['pin_h']) ? (float) $data['pin_h'] : null,
            array_values($data['mention_user_ids'] ?? []),
            $imageIds,
            $fieldName !== '' ? $fieldName : null,
        );

        return $this->created(['id' => (string) $comment->id]);
    }
}
