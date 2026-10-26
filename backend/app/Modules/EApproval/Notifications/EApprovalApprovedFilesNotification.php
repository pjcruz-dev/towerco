<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Sent from the request that approved the submission, not a queue worker.
 * The worker often cannot see the tenant file disk, so a queued copy arrived with no files.
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
                ->with(['form:id,name,metadata_json', 'requestor:id,name', 'attachments'])
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
                ->line(__('Document: **:document**', ['document' => $documentNo]))
                ->line(__('Form: :form', ['form' => $formName]));

            if ($copy['html_body']) {
                $message->line(new HtmlString($copy['lines'][0] ?? ''));
            } else {
                foreach ($copy['lines'] as $line) {
                    $message->line($line);
                }
            }

            $attached = 0;
            if ($submission !== null) {
                $disk = (string) config('toweros.tenant_files.disk', 'tenant_files');
                foreach ($submission->attachments as $attachment) {
                    if (! $attachment instanceof EApprovalAttachment || $attachment->isSuperseded()) {
                        continue;
                    }
                    $path = (string) $attachment->file_path;
                    $name = trim((string) $attachment->file_name);
                    if ($path === '' || $name === '' || ! Storage::disk($disk)->exists($path)) {
                        Log::warning('e_approval.approved_file_missing', [
                            'submission_id' => $this->submissionId,
                            'attachment_id' => (string) $attachment->id,
                            'path' => $path,
                        ]);

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

            $openId = (string) ($submission?->id ?? $this->submissionId);
            $buttons = $copy['buttons'];
            if ($buttons === null) {
                $message->action(
                    __('Open review'),
                    $resolver->urlForCurrentTenant(EApprovalFormMailConfig::buttonPath('open_submission', $openId)),
                );
            } elseif ($buttons !== []) {
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
