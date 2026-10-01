<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\TicketingTicket;
use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Records the signed-in user owns or is assigned, grouped by enabled module,
 * with the files stored on those records.
 */
final class MeRelatedRecordsService
{
    private const RECORD_LIMIT = 12;

    public function __construct(
        private readonly TenantEnabledModulesResolver $modules,
    ) {}

    /**
     * @return array{modules: list<array{key: string, label: string, records: list<array<string, mixed>>}>}
     */
    public function forUser(TenantUser $user): array
    {
        $enabled = $this->modules->resolveForCurrentTenant();
        $modules = [];

        if ($this->allowed($enabled, 'ticketing', $user, 'ticketing:view') && Schema::hasTable('ticketing_tickets')) {
            $modules[] = $this->ticketing($user);
        }

        if ($this->allowed($enabled, 'e_approval', $user, 'e_approval:view') && Schema::hasTable('e_approval_submissions')) {
            $modules[] = $this->eApproval($user);
        }

        if ($this->allowed($enabled, 'document_register', $user, 'documents:controlled:view') && Schema::hasTable('controlled_documents')) {
            $modules[] = $this->documents($user);
        }

        if ($this->allowed($enabled, 'doc_extract', $user, 'doc-extract:view') && Schema::hasTable('doc_extract_batches')) {
            $modules[] = $this->docExtract($user);
        }

        return ['modules' => $modules];
    }

    /**
     * @param  list<string>  $enabled
     */
    private function allowed(array $enabled, string $module, TenantUser $user, string $permission): bool
    {
        return in_array($module, $enabled, true) && $user->can($permission);
    }

    /**
     * @return array{key: string, label: string, records: list<array<string, mixed>>}
     */
    private function ticketing(TenantUser $user): array
    {
        $rows = TicketingTicket::query()
            ->where(function ($query) use ($user): void {
                $query->where('requester_id', $user->id)
                    ->orWhere('assignee_id', $user->id);
            })
            ->with(['attachments:id,ticket_id,file_name,mime_type,size_bytes'])
            ->orderByDesc('updated_at')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'ticket_number', 'title', 'status', 'requester_id', 'assignee_id']);

        $records = [];
        foreach ($rows as $ticket) {
            $relation = (string) $ticket->requester_id === (string) $user->id ? 'Requested' : 'Assigned';
            $attachments = [];
            foreach ($ticket->attachments as $file) {
                $attachments[] = $this->file(
                    (string) $file->id,
                    (string) $file->file_name,
                    $file->mime_type,
                    $file->size_bytes,
                    'ticketing',
                    (string) $ticket->id,
                );
            }
            $records[] = $this->record(
                (string) $ticket->id,
                (string) $ticket->title,
                trim($ticket->ticket_number.' · '.$relation.' · '.str_replace('_', ' ', (string) $ticket->status)),
                '/ticketing/tickets/'.$ticket->id,
                (string) $ticket->status,
                $attachments,
            );
        }

        return ['key' => 'ticketing', 'label' => 'Tickets', 'records' => $records];
    }

    /**
     * @return array{key: string, label: string, records: list<array<string, mixed>>}
     */
    private function eApproval(TenantUser $user): array
    {
        $rows = EApprovalSubmission::query()
            ->where('requestor_id', $user->id)
            ->with([
                'form:id,name',
                'attachments:id,submission_id,file_name',
            ])
            ->orderByDesc('updated_at')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'document_no', 'form_id', 'status']);

        $records = [];
        foreach ($rows as $submission) {
            $title = trim((string) ($submission->form?->name ?: $submission->document_no ?: 'Submission'));
            $attachments = [];
            foreach ($submission->attachments as $file) {
                $attachments[] = $this->file(
                    (string) $file->id,
                    (string) $file->file_name,
                    null,
                    null,
                    'e_approval',
                    (string) $submission->id,
                );
            }
            $records[] = $this->record(
                (string) $submission->id,
                $title,
                trim(((string) ($submission->document_no ?? '')).' · '.str_replace('_', ' ', (string) $submission->status), ' ·'),
                '/e-approval/submissions/'.$submission->id,
                (string) $submission->status,
                $attachments,
            );
        }

        return ['key' => 'e_approval', 'label' => 'E-Forms', 'records' => $records];
    }

    /**
     * @return array{key: string, label: string, records: list<array<string, mixed>>}
     */
    private function documents(TenantUser $user): array
    {
        $rows = ControlledDocument::query()
            ->where('created_by_id', $user->id)
            ->with(['revisions' => function ($query): void {
                $query->whereNotNull('stored_path')
                    ->where('stored_path', '!=', '')
                    ->orderByDesc('revision_number');
            }])
            ->orderByDesc('updated_at')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'document_code', 'title', 'status', 'current_revision']);

        $records = [];
        foreach ($rows as $document) {
            $attachments = [];
            foreach ($document->revisions as $revision) {
                $name = trim((string) ($revision->original_filename ?: ''));
                if ($name === '') {
                    $name = 'Revision '.$revision->revision_number;
                }
                $attachments[] = $this->file(
                    (string) $revision->id,
                    $name,
                    $revision->mime_type,
                    $revision->size_bytes,
                    'document_register',
                    (string) $document->id,
                );
            }
            $records[] = $this->record(
                (string) $document->id,
                (string) $document->title,
                trim(((string) ($document->document_code ?? '')).' · Rev '.$document->current_revision.' · '.str_replace('_', ' ', (string) $document->status), ' ·'),
                '/documents/controlled?document='.$document->id,
                (string) $document->status,
                $attachments,
            );
        }

        return ['key' => 'document_register', 'label' => 'Document register', 'records' => $records];
    }

    /**
     * @return array{key: string, label: string, records: list<array<string, mixed>>}
     */
    private function docExtract(TenantUser $user): array
    {
        $rows = DocExtractBatch::query()
            ->where('created_by_id', $user->id)
            ->with([
                'template:id,name',
                'documents' => function ($query): void {
                    $query->whereNull('purged_at')
                        ->orderByDesc('created_at');
                },
            ])
            ->orderByDesc('updated_at')
            ->limit(self::RECORD_LIMIT)
            ->get(['id', 'template_id', 'status', 'document_count']);

        $records = [];
        foreach ($rows as $batch) {
            $attachments = [];
            foreach ($batch->documents as $document) {
                $attachments[] = $this->file(
                    (string) $document->id,
                    (string) $document->original_filename,
                    $document->mime_type,
                    $document->size_bytes,
                    'doc_extract',
                    (string) $batch->id,
                );
            }
            $title = trim((string) ($batch->template?->name ?: 'Extraction batch'));
            $records[] = $this->record(
                (string) $batch->id,
                $title,
                trim(((int) $batch->document_count).' file'.((int) $batch->document_count === 1 ? '' : 's').' · '.str_replace('_', ' ', (string) $batch->status)),
                '/doc-extract/batches/'.$batch->id,
                (string) $batch->status,
                $attachments,
            );
        }

        return ['key' => 'doc_extract', 'label' => 'DocExtract', 'records' => $records];
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return array<string, mixed>
     */
    private function record(
        string $id,
        string $title,
        string $subtitle,
        string $href,
        string $status,
        array $attachments,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'href' => $href,
            'status' => $status,
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(
        string $id,
        string $fileName,
        mixed $mimeType,
        mixed $sizeBytes,
        string $source,
        string $recordId,
    ): array {
        return [
            'id' => $id,
            'file_name' => $fileName,
            'mime_type' => is_string($mimeType) && $mimeType !== '' ? $mimeType : null,
            'size_bytes' => is_numeric($sizeBytes) ? (int) $sizeBytes : null,
            'source' => $source,
            'record_id' => $recordId,
        ];
    }
}
