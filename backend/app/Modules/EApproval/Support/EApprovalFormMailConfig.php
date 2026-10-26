<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Services\EApprovalFormFieldChoicesResolver;
use App\Modules\EApproval\Services\EApprovalFormValueDisplayService;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Carbon;

/**
 * Per-form email copy and status words. Missing keys keep the product defaults.
 */
final class EApprovalFormMailConfig
{
    public const NOTE_MAX = 500;

    public const BODY_MAX = 8000;

    public const SUBJECT_MAX = 180;

    public const LABEL_MAX = 60;

    public const CC_MAX = 10;

    public const BUTTON_MAX = 2;

    /**
     * @var list<string>
     */
    public const STATUS_KEYS = [
        'pending',
        'approved',
        'rejected',
        'returned',
        'cancelled',
        'awaiting_dcf',
    ];

    /**
     * @var list<string>
     */
    public const EVENT_KEYS = [
        'submitted',
        'approval_assigned',
        'step_approved',
        'approved',
        'approved_files',
        'rejected',
        'returned',
        'cancelled',
    ];

    /**
     * @return array{
     *     notify_assignee: bool,
     *     note: string,
     *     subject: string,
     *     body: string,
     *     buttons: list<array{label: string, target: string}>|null,
     *     cc_user_ids: list<string>,
     *     notify_requestor_on_approve: bool,
     *     requestor_subject: string,
     *     requestor_body: string,
     *     requestor_buttons: list<array{label: string, target: string}>|null
     * }
     */
    public static function defaultMail(): array
    {
        return [
            'notify_assignee' => true,
            'note' => '',
            'subject' => '',
            'body' => '',
            'buttons' => null,
            'cc_user_ids' => [],
            'notify_requestor_on_approve' => false,
            'requestor_subject' => '',
            'requestor_body' => '',
            'requestor_buttons' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function statusWord(?array $metadata, string $status): string
    {
        $custom = self::customStatus($metadata, $status);

        return $custom !== '' ? $custom : EApprovalSubmissionStatus::label($status);
    }

    /**
     * Trail words on the step list. Unknown fallbacks stay as written.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function trailLabel(?array $metadata, string $fallback): string
    {
        $code = match ($fallback) {
            'Pending' => 'pending',
            'Approved' => 'approved',
            'Rejected' => 'rejected',
            'Cancelled' => 'cancelled',
            default => null,
        };

        if ($code === null) {
            return $fallback;
        }

        return self::statusWord($metadata, $code);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function eventEnabled(?array $metadata, string $event): bool
    {
        if (! in_array($event, self::EVENT_KEYS, true)) {
            return true;
        }

        $events = self::eventsNode($metadata);
        $row = $events[$event] ?? null;
        if (! is_array($row) || ! array_key_exists('enabled', $row)) {
            return true;
        }

        return $row['enabled'] === true || $row['enabled'] === 1 || $row['enabled'] === '1' || $row['enabled'] === 'true';
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function eventNote(?array $metadata, string $event): string
    {
        if (! in_array($event, self::EVENT_KEYS, true)) {
            return '';
        }

        $events = self::eventsNode($metadata);
        $row = $events[$event] ?? null;
        if (! is_array($row)) {
            return '';
        }

        return self::cleanNote($row['note'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $condition
     * @return array{notify_assignee: bool, note: string, cc_user_ids: list<string>, notify_requestor_on_approve: bool}
     */
    public static function mailFromCondition(array $condition): array
    {
        $raw = $condition['mail'] ?? null;

        return self::normalizeMail(is_array($raw) ? $raw : null);
    }

    /**
     * @param  array<string, mixed>|null  $mail
     * @return array{notify_assignee: bool, note: string, cc_user_ids: list<string>, notify_requestor_on_approve: bool}
     */
    public static function normalizeMail(?array $mail): array
    {
        $defaults = self::defaultMail();
        if ($mail === null) {
            return $defaults;
        }

        $ids = [];
        $rawIds = $mail['cc_user_ids'] ?? [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
                $value = trim((string) $id);
                if ($value === '' || in_array($value, $ids, true)) {
                    continue;
                }
                $ids[] = $value;
                if (count($ids) >= self::CC_MAX) {
                    break;
                }
            }
        }

        return [
            'notify_assignee' => ! array_key_exists('notify_assignee', $mail) || self::truthy($mail['notify_assignee']),
            'note' => self::cleanNote($mail['note'] ?? ''),
            'subject' => self::cleanSubject($mail['subject'] ?? ''),
            'body' => self::cleanBody($mail['body'] ?? ''),
            'buttons' => array_key_exists('buttons', $mail) ? self::normalizeButtons($mail['buttons']) : null,
            'cc_user_ids' => $ids,
            'notify_requestor_on_approve' => self::truthy($mail['notify_requestor_on_approve'] ?? false),
            'requestor_subject' => self::cleanSubject($mail['requestor_subject'] ?? ''),
            'requestor_body' => self::cleanBody($mail['requestor_body'] ?? ''),
            'requestor_buttons' => array_key_exists('requestor_buttons', $mail)
                ? self::normalizeButtons($mail['requestor_buttons'])
                : null,
        ];
    }

    /**
     * @param  array{
     *     notify_assignee: bool,
     *     note: string,
     *     subject: string,
     *     body: string,
     *     buttons: list<array{label: string, target: string}>|null,
     *     cc_user_ids: list<string>,
     *     notify_requestor_on_approve: bool,
     *     requestor_subject: string,
     *     requestor_body: string,
     *     requestor_buttons: list<array{label: string, target: string}>|null
     * }  $mail
     */
    public static function isDefaultMail(array $mail): bool
    {
        return $mail['notify_assignee'] === true
            && $mail['note'] === ''
            && $mail['subject'] === ''
            && $mail['body'] === ''
            && $mail['buttons'] === null
            && $mail['cc_user_ids'] === []
            && $mail['notify_requestor_on_approve'] === false
            && $mail['requestor_subject'] === ''
            && $mail['requestor_body'] === ''
            && $mail['requestor_buttons'] === null;
    }

    /**
     * @return list<array{label: string, target: string}>
     */
    public static function normalizeButtons(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $buttons = [];
        foreach ($raw as $button) {
            if (! is_array($button)) {
                continue;
            }
            $label = self::cleanButtonLabel($button['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $target = ($button['target'] ?? '') === 'open_approval' ? 'open_approval' : 'open_submission';
            $buttons[] = ['label' => $label, 'target' => $target];
            if (count($buttons) >= self::BUTTON_MAX) {
                break;
            }
        }

        return $buttons;
    }

    public static function buttonPath(string $target, string $submissionId): string
    {
        $tab = $target === 'open_approval' ? 'decide' : 'approvals';

        return "/e-approval/submissions/{$submissionId}?tab={$tab}";
    }

    /**
     * Copy for the mail that attaches the approved files. Missing settings keep the standard sentence and Open review.
     *
     * @return array{
     *     send: bool,
     *     subject: string,
     *     lines: list<string>,
     *     html_body: bool,
     *     buttons: list<array{label: string, target: string}>|null
     * }
     */
    public static function approvedFilesCopy(
        EApprovalSubmission $submission,
        string $prefix,
        string $documentNo,
        string $formName,
        string $requestorName,
    ): array {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        $empty = [
            'send' => false,
            'subject' => '',
            'lines' => [],
            'html_body' => false,
            'buttons' => null,
        ];
        if (! self::eventEnabled($metadata, 'approved_files')) {
            return $empty;
        }

        $template = self::storedEvent($metadata, 'approved_files');
        $standard = __('This request is fully approved. The current files are attached.');
        $subject = $template['subject'] !== ''
            ? trim($prefix.' '.self::fillTokens($template['subject'], $documentNo, $formName, '', $requestorName, '', $submission))
            : "{$prefix} Approved files — {$documentNo}";

        $htmlBody = false;
        if ($template['body'] !== '' && self::containsHtml($template['body'])) {
            $html = self::fillTokens(
                self::sanitizeMailHtml($template['body']),
                $documentNo,
                $formName,
                '',
                $requestorName,
                '',
                $submission,
                true,
            );
            $lines = $html === '' ? [$standard] : [$html];
            $htmlBody = $html !== '';
        } elseif ($template['body'] !== '') {
            $lines = self::bodyLines(self::fillTokens($template['body'], $documentNo, $formName, '', $requestorName, '', $submission));
            if ($lines === []) {
                $lines = [$standard];
            }
        } else {
            $lines = [$standard];
        }

        return [
            'send' => true,
            'subject' => $subject,
            'lines' => $lines,
            'html_body' => $htmlBody,
            'buttons' => $template['buttons'],
        ];
    }

    /**
     * Live design step at this order, then the snapshot captured at submit.
     *
     * @return array{label: string, mail: array{notify_assignee: bool, note: string, cc_user_ids: list<string>, notify_requestor_on_approve: bool}}
     */
    public static function stepPresentation(EApprovalSubmission $submission, int $stepOrder): array
    {
        $order = max(1, $stepOrder);
        $form = $submission->relationLoaded('form') ? $submission->form : null;
        if (! $form instanceof EApprovalForm && $submission->exists) {
            $submission->loadMissing('form');
            $form = $submission->form;
        }

        $steps = null;
        if ($form instanceof EApprovalForm && $form->relationLoaded('workflowTemplate')) {
            $template = $form->workflowTemplate;
            if ($template !== null && $template->relationLoaded('steps')) {
                $steps = $template->steps;
            } elseif ($template !== null && $template->exists) {
                $template->loadMissing('steps');
                $steps = $template->steps;
            }
        } elseif ($form instanceof EApprovalForm && $form->exists) {
            $form->loadMissing('workflowTemplate.steps');
            $steps = $form->workflowTemplate?->steps;
        }

        $matched = false;
        $label = '';
        $mail = self::defaultMail();

        if ($steps !== null) {
            foreach ($steps as $step) {
                if (! $step instanceof EApprovalWorkflowStep) {
                    continue;
                }
                if ($step->compiled_for_submission_id !== null) {
                    continue;
                }
                if ((int) $step->step_order !== $order) {
                    continue;
                }
                $matched = true;
                $stepLabel = self::cleanPublicLabel($step->label);
                if ($label === '' && $stepLabel !== '') {
                    $label = $stepLabel;
                }
                $candidate = self::mailFromCondition(is_array($step->condition) ? $step->condition : []);
                if (! self::isDefaultMail($candidate)) {
                    $mail = $candidate;
                }
            }
        }

        if (! $matched) {
            $snapshot = self::snapshotStep($submission, $order);
            if ($snapshot !== null) {
                $label = self::cleanPublicLabel($snapshot['label'] ?? '');
                $condition = is_array($snapshot['condition'] ?? null) ? $snapshot['condition'] : [];
                $mail = self::mailFromCondition($condition);
            }
        }

        return ['label' => $label, 'mail' => $mail];
    }

    /**
     * @return array{
     *     subject: string|null,
     *     lines: list<string>,
     *     custom_body: bool,
     *     html_body: bool,
     *     buttons: list<array{label: string, target: string}>|null
     * }
     */
    public static function mailDecoration(
        EApprovalSubmission $submission,
        string $event,
        ?string $actorName,
        ?int $stepOrder,
        string $prefix,
        string $documentNo,
        string $requestorName,
        string $formName,
    ): array {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        $stepEvents = ['approval_assigned', 'approval_assigned_revised', 'step_approved'];
        $presentation = in_array($event, $stepEvents, true)
            ? self::stepPresentation($submission, $stepOrder ?? (int) ($submission->current_step ?: 1))
            : ['label' => '', 'mail' => self::defaultMail()];

        $code = self::statusCodeForEvent($event);
        $custom = $code !== null ? self::customStatus($metadata, $code) : '';
        $stepLabel = $presentation['label'];
        $showStep = $stepLabel !== '' && $code === 'pending' && in_array($event, $stepEvents, true);

        $lines = [];
        if ($custom !== '' || $showStep) {
            $word = $custom !== '' ? $custom : EApprovalSubmissionStatus::label($code ?? 'pending');
            $text = $showStep ? $word.' — '.$stepLabel : $word;
            $lines[] = __('Status: :status', ['status' => $text]);
        }

        $template = self::templateForEvent($metadata, $event, $presentation['mail']);
        $actor = (string) $actorName;
        $customBody = $template['body'] !== '';
        $htmlBody = false;
        if ($customBody && self::containsHtml($template['body'])) {
            $html = self::fillTokens(
                self::sanitizeMailHtml($template['body']),
                $documentNo,
                $formName,
                $stepLabel,
                $requestorName,
                $actor,
                $submission,
                true,
            );
            if ($html === '') {
                $customBody = false;
            } else {
                $lines = [$html];
                $htmlBody = true;
            }
        } elseif ($customBody) {
            $lines = self::bodyLines(self::fillTokens($template['body'], $documentNo, $formName, $stepLabel, $requestorName, $actor, $submission));
        } else {
            $note = $template['note'];
            if ($note !== '') {
                $lines[] = self::fillTokens($note, $documentNo, $formName, $stepLabel, $requestorName, $actor, $submission);
            }
        }

        $subject = null;
        if ($template['subject'] !== '') {
            $subject = trim($prefix.' '.self::fillTokens($template['subject'], $documentNo, $formName, $stepLabel, $requestorName, $actor));
        } elseif ($custom !== '' && in_array($event, [
            'approved',
            'rejected',
            'returned',
            'cancelled',
            'awaiting_dcf',
            'approval_assigned',
            'approval_assigned_revised',
            'step_approved',
        ], true)) {
            $subject = $showStep
                ? "{$prefix} {$custom} — {$stepLabel} — {$documentNo}"
                : "{$prefix} {$custom} — {$documentNo}";
        }

        return [
            'subject' => $subject,
            'lines' => $lines,
            'custom_body' => $customBody,
            'html_body' => $htmlBody,
            'buttons' => $template['buttons'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $stepMail
     * @return array{subject: string, body: string, note: string, buttons: list<array{label: string, target: string}>|null}
     */
    private static function templateForEvent(?array $metadata, string $event, array $stepMail): array
    {
        $stepOpen = in_array($event, ['approval_assigned', 'approval_assigned_revised'], true);
        if ($stepOpen) {
            $shared = self::storedEvent($metadata, 'approval_assigned');

            return [
                'subject' => ($stepMail['subject'] ?? '') !== '' ? (string) $stepMail['subject'] : $shared['subject'],
                'body' => ($stepMail['body'] ?? '') !== '' ? (string) $stepMail['body'] : $shared['body'],
                'note' => (string) ($stepMail['note'] ?? '') !== '' ? (string) $stepMail['note'] : $shared['note'],
                'buttons' => $stepMail['buttons'] ?? $shared['buttons'],
            ];
        }
        if ($event === 'step_approved') {
            $shared = self::storedEvent($metadata, 'step_approved');

            return [
                'subject' => ($stepMail['requestor_subject'] ?? '') !== '' ? (string) $stepMail['requestor_subject'] : $shared['subject'],
                'body' => ($stepMail['requestor_body'] ?? '') !== '' ? (string) $stepMail['requestor_body'] : $shared['body'],
                'note' => $shared['note'],
                'buttons' => $stepMail['requestor_buttons'] ?? $shared['buttons'],
            ];
        }

        $key = match ($event) {
            'submitted' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            default => null,
        };

        return $key === null
            ? ['subject' => '', 'body' => '', 'note' => '', 'buttons' => null]
            : self::storedEvent($metadata, $key);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{subject: string, body: string, note: string, buttons: list<array{label: string, target: string}>|null}
     */
    private static function storedEvent(?array $metadata, string $key): array
    {
        $row = self::eventsNode($metadata)[$key] ?? null;
        if (! is_array($row)) {
            return ['subject' => '', 'body' => '', 'note' => '', 'buttons' => null];
        }

        return [
            'subject' => self::cleanSubject($row['subject'] ?? ''),
            'body' => self::cleanBody($row['body'] ?? ''),
            'note' => self::cleanNote($row['note'] ?? ''),
            'buttons' => array_key_exists('buttons', $row) ? self::normalizeButtons($row['buttons']) : null,
        ];
    }

    /**
     * @return list<string>
     */
    private static function bodyLines(string $body): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $kept[] = $trimmed;
            }
        }

        return $kept;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private static function customStatus(?array $metadata, string $status): string
    {
        $key = strtolower(trim($status));
        if (! in_array($key, self::STATUS_KEYS, true)) {
            return '';
        }

        $root = is_array($metadata) ? ($metadata['notifications'] ?? null) : null;
        if (! is_array($root)) {
            return '';
        }
        $statuses = $root['statuses'] ?? null;
        if (! is_array($statuses) || ! array_key_exists($key, $statuses)) {
            return '';
        }

        return self::cleanPublicLabel($statuses[$key]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private static function eventsNode(?array $metadata): array
    {
        $root = is_array($metadata) ? ($metadata['notifications'] ?? null) : null;
        if (! is_array($root)) {
            return [];
        }
        $events = $root['events'] ?? null;

        return is_array($events) ? $events : [];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private static function eventNoteForMail(?array $metadata, string $event): string
    {
        $key = match ($event) {
            'submitted' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            default => null,
        };

        return $key === null ? '' : self::eventNote($metadata, $key);
    }

    private static function statusCodeForEvent(string $event): ?string
    {
        return match ($event) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            'awaiting_dcf' => 'awaiting_dcf',
            'approval_assigned', 'approval_assigned_revised', 'step_approved', 'submitted', 'resubmitted_resume', 'resubmitted_restart' => 'pending',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function snapshotStep(EApprovalSubmission $submission, int $stepOrder): ?array
    {
        $raw = $submission->workflow_snapshot_json;
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (! is_array($decoded)) {
            return null;
        }
        $steps = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];
        foreach ($steps as $step) {
            if (! is_array($step) || (int) ($step['step_order'] ?? 0) !== $stepOrder) {
                continue;
            }

            return $step;
        }

        return null;
    }

    private static function fillTokens(
        string $note,
        string $documentNo,
        string $formName,
        string $stepLabel,
        string $requestorName,
        ?string $actorName,
        ?EApprovalSubmission $submission = null,
        bool $html = false,
    ): string {
        $map = [
            '{{document_no}}' => $documentNo,
            '{{form_name}}' => $formName,
            '{{step_label}}' => $stepLabel,
            '{{requestor_name}}' => $requestorName,
            '{{actor_name}}' => trim((string) $actorName),
        ];

        $fileLinks = [];
        if ($submission instanceof EApprovalSubmission && $submission->exists) {
            foreach (self::submissionFieldTokens($submission) as $key => $value) {
                if (! array_key_exists($key, $map)) {
                    $map[$key] = $value;
                }
            }
            if ($html) {
                $fileLinks = self::fileTokenLinks($submission);
            }
        }

        if ($html) {
            $map = array_map(
                static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $map,
            );
            foreach ($fileLinks as $key => $link) {
                $map[$key] = $link;
            }
        }

        $note = preg_replace('/(?<=[\p{L}\p{N}])(\{\{[A-Za-z0-9_]+\}\})/u', ' $1', $note) ?? $note;

        return preg_replace_callback(
            '/\{\{([A-Za-z0-9_]+)\}\}/',
            static function (array $match) use ($map): string {
                $key = $match[0];

                return array_key_exists($key, $map) ? $map[$key] : $key;
            },
            $note,
        ) ?? $note;
    }

    /**
     * @return array<string, string>
     */
    private static function submissionFieldTokens(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['values.field', 'form.fields']);
        $display = app(EApprovalFormValueDisplayService::class);
        $users = $display->approverUsersById($submission->values);
        $choices = app(EApprovalFormFieldChoicesResolver::class);
        $map = [];

        foreach ($submission->values as $value) {
            $field = $value->relationLoaded('field') ? $value->field : null;
            $name = $field?->name;
            if (! is_string($name) || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                continue;
            }
            $raw = $value->value;
            $rawText = is_string($raw) ? $raw : (is_scalar($raw) ? (string) $raw : '');
            $options = is_array($field?->options) ? $field->options : null;
            $shown = $display->resolveDisplayValue($field?->type, $rawText, $users, $options);
            $shown = trim((string) ($shown ?? ''));
            if ($shown !== '' && $shown === trim($rawText) && $submission->form instanceof EApprovalForm) {
                foreach ($choices->choicesForFieldName($submission->form, $name) as $choice) {
                    if ($choice['value'] === trim($rawText) && $choice['label'] !== '' && $choice['label'] !== $choice['value']) {
                        $shown = $choice['label'];
                        break;
                    }
                }
            }
            if ($field?->type === 'date' && $shown !== '') {
                try {
                    $shown = Carbon::parse($shown)->format('M j, Y');
                } catch (\Throwable) {
                    // Keep the stored text when it is not a date.
                }
            }
            $map['{{'.$name.'}}'] = $shown;
        }

        $fields = $submission->form?->fields;
        if ($fields !== null) {
            foreach ($fields as $field) {
                $name = $field->name;
                if (! is_string($name) || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                    continue;
                }
                $key = '{{'.$name.'}}';
                if (! array_key_exists($key, $map)) {
                    $map[$key] = '';
                }
            }
        }

        return $map;
    }

    /**
     * File chips become links to the request. The mail still carries the file itself.
     *
     * @return array<string, string>
     */
    private static function fileTokenLinks(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['values.field', 'form.fields', 'attachments']);
        $url = app(TenantAppUrlResolver::class)->urlForCurrentTenant(
            '/e-approval/submissions/'.$submission->id.'?tab=request',
        );
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $links = [];

        $fields = $submission->form?->fields;
        $names = [];
        if ($fields !== null) {
            foreach ($fields as $field) {
                if (! in_array($field->type, ['file', 'camera'], true)) {
                    continue;
                }
                $name = $field->name;
                if (is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
                    $names[] = $name;
                }
            }
        }

        foreach ($names as $name) {
            $parts = [];
            foreach ($submission->attachments as $attachment) {
                if (! $attachment instanceof EApprovalAttachment || $attachment->isSuperseded()) {
                    continue;
                }
                if ((string) ($attachment->field_name ?? '') !== $name) {
                    continue;
                }
                $fileName = trim((string) $attachment->file_name);
                if ($fileName === '') {
                    continue;
                }
                $safeName = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $parts[] = '<a href="'.$safeUrl.'">'.$safeName.'</a>';
            }
            if ($parts !== []) {
                $links['{{'.$name.'}}'] = implode(', ', $parts);
            }
        }

        return $links;
    }

    private static function containsHtml(string $value): bool
    {
        return preg_match('/<(?:p|div|span|table|thead|tbody|tr|td|th|ul|ol|li|h[1-4]|blockquote|strong|b|em|i|u|a|br|font)\b/i', $value) === 1;
    }

    public static function sanitizeMailHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '' || ! self::containsHtml($html)) {
            return self::containsHtml($html) ? '' : trim(strip_tags($html));
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="mail-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new \DOMXPath($document))->query('//*[@id="mail-root"]')->item(0);
        if (! $root instanceof \DOMElement) {
            return '';
        }

        self::sanitizeMailChildren($root);
        $root->removeAttribute('id');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNote(mixed $value): string
    {
        return self::clipText($value, self::NOTE_MAX);
    }

    private static function cleanBody(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > self::BODY_MAX) {
            $text = mb_substr($text, 0, self::BODY_MAX);
        }
        if (! self::containsHtml($text)) {
            return trim(strip_tags($text));
        }

        return self::sanitizeMailHtml($text);
    }

    private static function sanitizeMailChildren(\DOMNode $node): void
    {
        $allowed = ['p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (! $child instanceof \DOMElement) {
                $node->removeChild($child);

                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'img'], true)) {
                $node->removeChild($child);

                continue;
            }
            if ($tag === 'font') {
                $converted = self::convertFontElement($child);
                if (! $converted instanceof \DOMElement) {
                    continue;
                }
                $child = $converted;
                $tag = 'span';
            }
            if ($child->hasAttribute('data-mail-token')) {
                $token = (string) $child->getAttribute('data-mail-token');
                $text = preg_match('/^[A-Za-z0-9_]+$/', $token) === 1 ? '{{'.$token.'}}' : ($child->textContent ?? '');
                $node->replaceChild($child->ownerDocument->createTextNode($text), $child);

                continue;
            }
            if (! in_array($tag, $allowed, true)) {
                self::sanitizeMailChildren($child);
                while ($child->firstChild instanceof \DOMNode) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::cleanMailAttributes($child, $tag);
            self::sanitizeMailChildren($child);
        }
    }

    private static function convertFontElement(\DOMElement $element): ?\DOMElement
    {
        $document = $element->ownerDocument;
        if (! $document instanceof \DOMDocument) {
            return null;
        }
        $span = $document->createElement('span');
        $style = [];
        $face = trim((string) $element->getAttribute('face'));
        if ($face !== '') {
            $style[] = 'font-family: '.preg_replace('/[^A-Za-z0-9 ,\'-]/', '', $face);
        }
        $color = trim((string) $element->getAttribute('color'));
        if (self::allowedColor($color)) {
            $style[] = 'color: '.$color;
        }
        while ($element->firstChild instanceof \DOMNode) {
            $span->appendChild($element->firstChild);
        }
        if ($style !== []) {
            $span->setAttribute('style', implode('; ', $style));
        }
        $element->parentNode?->replaceChild($span, $element);

        return $span;
    }

    private static function cleanMailAttributes(\DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? self::allowedHref((string) $element->getAttribute('href')) : null;
        $style = self::allowedStyle((string) $element->getAttribute('style'));
        if ($tag === 'table') {
            $style = self::mergeStyle($style, 'width:100%;border-collapse:collapse');
        }
        if ($tag === 'td' || $tag === 'th') {
            $style = self::mergeStyle($style, 'border:1px solid rgb(203, 213, 225);padding:4px 8px');
        }
        $colspan = ($tag === 'td' || $tag === 'th') ? (string) $element->getAttribute('colspan') : '';
        $rowspan = ($tag === 'td' || $tag === 'th') ? (string) $element->getAttribute('rowspan') : '';
        while ($element->attributes->length > 0) {
            $element->removeAttribute($element->attributes->item(0)->nodeName);
        }
        if ($href !== null) {
            $element->setAttribute('href', $href);
        }
        if ($style !== '') {
            $element->setAttribute('style', $style);
        }
        if (preg_match('/^[1-9]\d?$/', $colspan) === 1) {
            $element->setAttribute('colspan', $colspan);
        }
        if (preg_match('/^[1-9]\d?$/', $rowspan) === 1) {
            $element->setAttribute('rowspan', $rowspan);
        }
    }

    private static function mergeStyle(string $style, string $extra): string
    {
        $style = trim($style, " \t\n\r\0\x0B;");

        return $style === '' ? $extra : $style.'; '.$extra;
    }

    private static function allowedHref(string $href): ?string
    {
        $href = trim($href);
        if (preg_match('/^mailto:([^\\s<>]+@[^\\s<>]+)$/i', $href) === 1) {
            return $href;
        }
        if (preg_match('/^https?:\\/\\//i', $href) !== 1) {
            return null;
        }
        $parts = parse_url($href);

        return is_array($parts) && isset($parts['scheme'], $parts['host']) ? $href : null;
    }

    private static function allowedStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $rule) {
            $parts = explode(':', $rule, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($value === '' || preg_match('/url\\s*\\(|expression|javascript:/i', $value) === 1) {
                continue;
            }
            $ok = match ($property) {
                'font-family' => preg_match('/^[A-Za-z0-9 ,\'-]+$/', $value) === 1,
                'font-size' => preg_match('/^\\d+(?:\\.\\d+)?(?:px|pt|em|rem|%)$/', $value) === 1,
                'color', 'background-color' => self::allowedColor($value),
                'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true),
                'font-weight' => preg_match('/^(?:normal|bold|[1-9]00)$/', $value) === 1,
                'font-style' => in_array(strtolower($value), ['normal', 'italic'], true),
                'text-decoration' => in_array(strtolower($value), ['none', 'underline', 'line-through'], true),
                'width' => preg_match('/^\\d+(?:\\.\\d+)?(?:%|px)$/', $value) === 1,
                'border-collapse' => strtolower($value) === 'collapse',
                'border' => preg_match('/^\\d+px solid rgb\\(\\d{1,3}, \\d{1,3}, \\d{1,3}\\)$/', $value) === 1,
                'padding' => preg_match('/^\\d+px(?: \\d+px){0,3}$/', $value) === 1,
                default => false,
            };
            if ($ok) {
                $kept[] = $property.': '.$value;
            }
        }

        return implode('; ', $kept);
    }

    private static function allowedColor(string $value): bool
    {
        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1
            || preg_match('/^rgb\\(\\s*\\d{1,3}\\s*,\\s*\\d{1,3}\\s*,\\s*\\d{1,3}\\s*\\)$/i', $value) === 1;
    }

    private static function cleanSubject(mixed $value): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        return $text === '' ? '' : mb_substr($text, 0, self::SUBJECT_MAX);
    }

    private static function cleanButtonLabel(mixed $value): string
    {
        return str_replace(['[', ']', '(', ')'], '', self::cleanPublicLabel($value));
    }

    private static function clipText(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $max);
    }

    public static function cleanPublicLabel(mixed $value): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, self::LABEL_MAX);
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
