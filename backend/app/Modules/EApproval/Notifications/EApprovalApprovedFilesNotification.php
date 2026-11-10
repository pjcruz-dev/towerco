<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentPublishedFileToken;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Links to the current published file. The message does not attach a copy,
 * so a later revision does not leave people holding the old file.
 */
final class EApprovalApprovedFilesNotification extends Notification
{
    public function __construct(
        private readonly string $submissionId,
        private readonly ?string $tenantId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantId = $this->tenantId ?? (tenant()?->getTenantKey());

        return app(TenantAppUrlResolver::class)->runForTenant($tenantId, function (): MailMessage {
            $resolver = app(TenantAppUrlResolver::class);
            $submission = EApprovalSubmission::query()
                ->with(['form:id,name,metadata_json', 'requestor:id,name', 'values.field'])
                ->find($this->submissionId);
            $documentNo = (string) ($submission?->document_no ?? '');
            $formName = $submission?->form?->name ?? __('Form');
            $requestorName = (string) ($submission?->requestor?->name ?? '');
            $brand = $resolver->mailBrandLabel();
            $prefix = $resolver->subjectPrefix();
            $copy = $submission instanceof EApprovalSubmission
                ? EApprovalFormMailConfig::approvedFilesCopy($submission, $prefix, $documentNo, $formName, $requestorName)
                : [
                    'send' => true,
                    'subject' => "{$prefix} Approved files — {$documentNo}",
                    'lines' => [__('This request is fully approved. The current files are attached.')],
                    'html_body' => false,
                    'buttons' => null,
                ];

            $message = (new MailMessage)
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->from((string) config('mail.from.address'), $brand)
                ->subject($copy['subject'])
                ->greeting("{$brand} — ".__('E-Forms'))
                ->line(__('Document: **:document**', ['document' => $documentNo]));

            $controlled = $this->isControlledPublication($submission);
            if ($controlled) {
                $document = $this->publishedDocument($submission);
                $message->line(__('Open the published file. This link opens only the revision that is published now. When a new revision is published, the same link opens that file. An obsolete revision cannot be opened from it.'));
                if ($document !== null && $this->replacesPrevious((string) $document->document_code)) {
                    $message->line(__('This publication replaces the previous revision.'));
                }
                if ($document !== null) {
                    $token = ControlledDocumentPublishedFileToken::sign((string) $document->id);
                    $path = '/documents/controlled/published/'.$document->id.'?token='.rawurlencode($token);
                    $message->action(
                        __('Open published file'),
                        $resolver->urlForCurrentTenant($path),
                    );
                }

                return $message->salutation(__('Regards,')."\n".$brand);
            }

            $message->line(__('Form: :form', ['form' => $formName]));

            if ($copy['html_body']) {
                $message->line(new HtmlString($copy['lines'][0] ?? ''));
            } else {
                foreach ($copy['lines'] as $line) {
                    $message->line($line);
                }
            }

            $this->attachFiles($message, $submission);

            $openId = (string) ($submission?->id ?? $this->submissionId);
            $buttons = $copy['buttons'];
            if ($buttons === null || $buttons === []) {
                $message->action(
                    __('Open review'),
                    $resolver->urlForCurrentTenant(EApprovalFormMailConfig::buttonPath('open_submission', $openId)),
                );
            } else {
                $first = $buttons[0];
                $message->action(
                    $first['label'],
                    $resolver->urlForCurrentTenant(EApprovalFormMailConfig::buttonPath($first['target'], $openId)),
                );
                if (isset($buttons[1])) {
                    $second = $buttons[1];
                    $secondUrl = $resolver->urlForCurrentTenant(EApprovalFormMailConfig::buttonPath($second['target'], $openId));
                    $message->line('['.$second['label'].']('.$secondUrl.')');
                }
            }

            return $message->salutation(__('Regards,')."\n".$brand);
        });
    }

    private function isControlledPublication(?EApprovalSubmission $submission): bool
    {
        if ($submission === null) {
            return false;
        }

        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (ControlledDocumentSyncConfig::parse($metadata) !== null) {
            return true;
        }

        $code = $this->documentCode($submission);

        return $code !== '' && ControlledDocument::query()->where('document_code', $code)->exists();
    }

    private function attachFiles(MailMessage $message, ?EApprovalSubmission $submission): void
    {
        $attached = 0;
        if ($submission !== null) {
            $submission->loadMissing('attachments');
            $disk = (string) config('toweros.tenant_files.disk', 'tenant_files');
            foreach ($submission->attachments as $attachment) {
                if (! $attachment instanceof EApprovalAttachment || $attachment->isSuperseded()) {
                    continue;
                }
                $path = (string) $attachment->file_path;
                $name = trim((string) $attachment->file_name);
                if ($path === '' || $name === '' || ! Storage::disk($disk)->exists($path)) {
                    continue;
                }
                $bytes = Storage::disk($disk)->get($path);
                if (! is_string($bytes) || $bytes === '') {
                    continue;
                }
                $message->attachData($bytes, $name, [
                    'mime' => $this->mimeForFileName($name),
                ]);
                $attached++;
            }
        }

        if ($attached === 0) {
            $message->line(__('The approved files could not be attached. Open the request to download them.'));
        }
    }

    private function documentCode(?EApprovalSubmission $submission): string
    {
        if ($submission === null) {
            return '';
        }

        $submission->loadMissing('values.field');
        foreach ($submission->values as $value) {
            if ((string) ($value->field?->name ?? '') !== 'document_code') {
                continue;
            }
            $text = trim((string) $value->value);
            if ($text !== '') {
                return $text;
            }
        }

        return trim((string) $submission->document_no);
    }

    private function publishedDocument(?EApprovalSubmission $submission): ?ControlledDocument
    {
        $code = $this->documentCode($submission);
        if ($code === '') {
            return null;
        }

        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if ($document === null || (string) $document->status !== ControlledDocumentStatus::PUBLISHED) {
            return null;
        }

        return $document;
    }

    private function replacesPrevious(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if ($document === null) {
            return false;
        }

        return $document->revisions()
            ->where('status', ControlledDocumentRevisionStatus::SUPERSEDED)
            ->exists();
    }

    private function mimeForFileName(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }
}
