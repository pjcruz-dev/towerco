<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Services\AssistantRevertStore;
use App\Modules\AiAssistant\Support\AssistantAudience;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormOutboundFile;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Tenant administrator only. Removes one named E-Forms form, its submissions, and the stored attachment files.
 */
final class DeleteEApprovalFormAction implements AssistantActionInterface
{
    public function __construct(
        private readonly EApprovalFileStorageService $files,
        private readonly TenantActivityLogger $activity,
        private readonly AssistantRevertStore $revert,
    ) {}

    public function name(): string
    {
        return 'delete_eapproval_form';
    }

    public function description(): string
    {
        return 'List one or more named E-Forms, with every submission and attachment, and wait for confirmation before deleting them. Tenant administrator only.';
    }

    public function requiredModule(): ?string
    {
        return 'e_approval';
    }

    public function requiredDomainPermissions(): array
    {
        return ['e_approval:forms:manage'];
    }

    public function plannerArgumentRules(): array
    {
        return [
            'form_name' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function argumentRules(): array
    {
        return $this->plannerArgumentRules();
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $this->assertTenantAdmin($viewer);
        $forms = [];
        foreach ($this->namesFrom($args, $question) as $name) {
            $forms[] = $this->findForm($name);
        }
        $inventory = $this->inventory($forms);
        $names = array_map(static fn (EApprovalForm $form): string => (string) $form->name, $forms);

        return new ActionProposalDraft(
            action: $this->name(),
            title: count($forms) === 1 ? 'Confirm deletion' : 'Confirm deletions',
            summary: sprintf(
                'Nothing is deleted yet. This will remove %d form%s, %d submission%s, and %d attachment%s.',
                count($forms),
                count($forms) === 1 ? '' : 's',
                $inventory['submissions'],
                $inventory['submissions'] === 1 ? '' : 's',
                $inventory['attachments'],
                $inventory['attachments'] === 1 ? '' : 's',
            ),
            payload: [
                'form_ids' => array_map(static fn (EApprovalForm $form): string => (string) $form->id, $forms),
                'form_names' => $names,
            ],
            preview: [
                'records' => $inventory['lines'],
            ],
            moduleKey: 'e_approval',
            confirmLabel: 'Delete listed data',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $this->assertTenantAdmin($viewer);
        $ids = $payload['form_ids'] ?? null;
        if (! is_array($ids) || $ids === []) {
            $single = trim((string) ($payload['form_id'] ?? ''));
            $ids = $single !== '' ? [$single] : [];
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== '')));
        $forms = EApprovalForm::query()->whereIn('id', $ids)->get();
        if ($forms->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'form_name' => ['One of those forms is no longer there.'],
            ]);
        }

        $this->rememberRevert($forms);

        $submissionCount = 0;
        $attachmentCount = 0;
        $paths = [];
        $names = [];

        DB::connection('tenant')->transaction(function () use ($forms, &$submissionCount, &$attachmentCount, &$paths, &$names): void {
            foreach ($forms as $form) {
                if (! $form instanceof EApprovalForm) {
                    continue;
                }
                $removed = $this->removeForm($form);
                $submissionCount += $removed['submissions'];
                $attachmentCount += $removed['attachments'];
                $paths = array_merge($paths, $removed['paths']);
                $names[] = $removed['name'];
            }
        });

        foreach ($paths as $path) {
            $this->files->deleteIfExists($path);
        }

        $label = sprintf(
            'Removed %s, %d submission%s, and %d attachment%s.',
            implode(', ', $names),
            $submissionCount,
            $submissionCount === 1 ? '' : 's',
            $attachmentCount,
            $attachmentCount === 1 ? '' : 's',
        );

        $this->activity->record(
            module: 'e_approval',
            action: 'eapproval.form_deleted',
            summary: $label,
            entityType: 'e_approval_form',
            entityId: (string) ($ids[0] ?? ''),
            entityLabel: implode(', ', $names),
            actor: $viewer,
            metadata: [
                'forms' => $names,
                'submissions' => $submissionCount,
                'attachments' => $attachmentCount,
            ],
            category: 'data_change',
            severity: 'high',
        );

        return new ActionExecutionResult(
            ok: true,
            entityType: 'e_approval_form',
            entityId: (string) ($ids[0] ?? ''),
            entityLabel: $label,
            meta: [
                'form_names' => $names,
                'submissions' => $submissionCount,
                'attachments' => $attachmentCount,
            ],
        );
    }

    /**
     * @return array{name: string, submissions: int, attachments: int, paths: list<string>}
     */
    private function removeForm(EApprovalForm $form): array
    {
        $submissionIds = EApprovalSubmission::query()
            ->where('form_id', $form->id)
            ->pluck('id')
            ->all();
        $attachmentPaths = $submissionIds === []
            ? []
            : EApprovalAttachment::query()
                ->whereIn('submission_id', $submissionIds)
                ->pluck('file_path')
                ->all();
        $outboundPaths = EApprovalFormOutboundFile::query()
            ->where('form_id', $form->id)
            ->pluck('file_path')
            ->all();
        $logo = trim((string) ($form->brand_logo_url ?? ''));
        $paths = array_values(array_filter(array_merge(
            $attachmentPaths,
            $outboundPaths,
            ($logo !== '' && ! str_starts_with($logo, 'http')) ? [$logo] : [],
        ), static fn (mixed $path): bool => is_string($path) && $path !== ''));

        if ($submissionIds !== []) {
            EApprovalSubmission::query()
                ->whereIn('parent_submission_id', $submissionIds)
                ->update(['parent_submission_id' => null]);
            EApprovalSubmission::query()->whereIn('id', $submissionIds)->delete();
        }
        $form->delete();

        return [
            'name' => (string) $form->name,
            'submissions' => count($submissionIds),
            'attachments' => count($attachmentPaths),
            'paths' => $paths,
        ];
    }

    private function assertTenantAdmin(TenantUser $viewer): void
    {
        if (! AssistantAudience::isTenantAdmin($viewer)) {
            throw ValidationException::withMessages([
                'role' => ['Only the tenant administrator can delete a form and its submissions.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, EApprovalForm>  $forms
     */
    private function rememberRevert(Collection $forms): void
    {
        $lines = ['Revert restores this confirmed deletion.'];
        $tables = [];
        $files = [];
        $formIds = $forms->pluck('id')->all();
        $submissionIds = EApprovalSubmission::query()->whereIn('form_id', $formIds)->pluck('id')->all();

        $tables[] = $this->rows('e_approval_forms', 'id', $formIds);
        foreach (['e_approval_form_fields', 'e_approval_workflow_templates', 'e_approval_form_outbound_files'] as $table) {
            $tables[] = $this->rows($table, 'form_id', $formIds);
        }
        $templateIds = [];
        foreach ($tables as $bundle) {
            if (($bundle['table'] ?? '') !== 'e_approval_workflow_templates') {
                continue;
            }
            foreach ($bundle['rows'] as $row) {
                if (isset($row['id'])) {
                    $templateIds[] = $row['id'];
                }
            }
        }
        $tables[] = $this->rows('e_approval_workflow_steps', 'template_id', $templateIds);
        $tables[] = $this->rows('e_approval_submissions', 'id', $submissionIds);
        foreach (['e_approval_form_values', 'e_approval_attachments', 'e_approval_request_approvals', 'e_approval_comments', 'e_approval_submission_followups'] as $table) {
            $tables[] = $this->rows($table, 'submission_id', $submissionIds);
        }

        foreach ($forms as $form) {
            $lines[] = 'Form: '.$form->name;
        }
        foreach ($tables as $bundle) {
            if (($bundle['table'] ?? '') !== 'e_approval_attachments' && ($bundle['table'] ?? '') !== 'e_approval_form_outbound_files') {
                continue;
            }
            foreach ($bundle['rows'] as $row) {
                $path = (string) ($row['file_path'] ?? '');
                $trash = $this->revert->copyAside($path);
                if ($trash !== null) {
                    $files[] = ['path' => $path, 'trash' => $trash];
                }
                if (isset($row['file_name'])) {
                    $lines[] = 'File '.$row['file_name'];
                }
            }
        }

        $this->revert->remember([
            'lines' => $lines,
            'tables' => array_values(array_filter($tables, static fn (array $bundle): bool => $bundle['rows'] !== [])),
            'files' => $files,
        ]);
    }

    /**
     * @param  list<mixed>  $ids
     * @return array{table: string, rows: list<array<string, mixed>>}
     */
    private function rows(string $table, string $column, array $ids): array
    {
        if ($ids === [] || ! Schema::connection('tenant')->hasTable($table)) {
            return ['table' => $table, 'rows' => []];
        }

        $rows = [];
        foreach (DB::connection('tenant')->table($table)->whereIn($column, $ids)->get() as $row) {
            $rows[] = (array) $row;
        }

        return ['table' => $table, 'rows' => $rows];
    }

    /**
     * @param  list<EApprovalForm>  $forms
     * @return array{submissions: int, attachments: int, lines: list<string>}
     */
    private function inventory(array $forms): array
    {
        $lines = [];
        $submissionCount = 0;
        $attachmentCount = 0;

        foreach ($forms as $form) {
            $lines[] = 'Form: '.$form->name;
            $submissions = EApprovalSubmission::query()
                ->where('form_id', $form->id)
                ->with([
                    'requestor:id,name,email',
                    'attachments:id,submission_id,file_name',
                ])
                ->orderBy('document_no')
                ->get();
            $outbound = EApprovalFormOutboundFile::query()
                ->where('form_id', $form->id)
                ->orderBy('file_name')
                ->get();

            if ($submissions->isEmpty() && $outbound->isEmpty()) {
                $lines[] = 'No submissions or files';
            }

            foreach ($submissions as $submission) {
                $submissionCount++;
                $who = trim((string) ($submission->requestor?->email ?? $submission->external_submitter_email ?? ''));
                $lines[] = trim(sprintf(
                    'Submission %s · %s%s',
                    (string) $submission->document_no,
                    (string) $submission->status,
                    $who !== '' ? ' · '.$who : '',
                ));
                $files = $submission->attachments;
                if ($files->isEmpty()) {
                    $lines[] = 'No attachments on '.(string) $submission->document_no;

                    continue;
                }
                foreach ($files as $attachment) {
                    $attachmentCount++;
                    $lines[] = 'Attachment '.(string) $attachment->file_name;
                }
            }

            foreach ($outbound as $file) {
                $attachmentCount++;
                $lines[] = 'Form file '.(string) $file->file_name;
            }
        }

        return [
            'submissions' => $submissionCount,
            'attachments' => $attachmentCount,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<string>
     */
    private function namesFrom(array $args, string $question): array
    {
        $given = trim((string) ($args['form_name'] ?? ''));
        if ($given === '') {
            $given = trim($question);
        }
        $parts = preg_split('/\s+\|\s+/u', $given) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if (mb_strlen($part) < 3) {
                continue;
            }
            $names[$part] = $part;
        }
        if ($names === []) {
            throw ValidationException::withMessages([
                'form_name' => ['Name the form to remove.'],
            ]);
        }

        return array_values($names);
    }

    private function findForm(string $name): EApprovalForm
    {
        $needle = mb_strtolower($name);
        $exact = EApprovalForm::query()
            ->whereRaw('LOWER(name) = ?', [$needle])
            ->limit(2)
            ->get();
        if ($exact->count() === 1) {
            /** @var EApprovalForm $form */
            $form = $exact->first();

            return $form;
        }
        if ($exact->count() > 1) {
            throw ValidationException::withMessages([
                'form_name' => ['More than one form uses that name. Name is not unique.'],
            ]);
        }

        $like = str_replace(['%', '_'], ['\\%', '\\_'], $needle);
        /** @var Collection<int, EApprovalForm> $matches */
        $matches = EApprovalForm::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$like.'%'])
            ->orderBy('name')
            ->limit(6)
            ->get();
        if ($matches->count() === 1) {
            /** @var EApprovalForm $form */
            $form = $matches->first();

            return $form;
        }
        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'form_name' => [sprintf('No form matches %s.', $name)],
            ]);
        }

        $names = $matches->pluck('name')->implode(', ');
        throw ValidationException::withMessages([
            'form_name' => [sprintf('More than one form matches. Name one of: %s.', $names)],
        ]);
    }
}
