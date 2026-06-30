<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class EApprovalSubmissionAttachmentValidator
{
    public function __construct(
        private readonly EApprovalFileStorageService $files,
    ) {}

    public function assertCanStore(
        EApprovalSubmission $submission,
        UploadedFile $file,
        ?string $fieldName,
    ): void {
        $this->files->assertUploadAllowed($file);

        if ($fieldName === null || trim($fieldName) === '') {
            return;
        }

        $submission->loadMissing(['form.fields', 'attachments']);
        $field = $submission->form?->fields
            ->first(static fn (EApprovalFormField $candidate) => (string) $candidate->name === $fieldName);

        if ($field === null || (string) $field->type !== 'file') {
            throw ValidationException::withMessages([
                'field_name' => [__('Uploaded file does not match a file field on this form.')],
            ]);
        }

        $validation = is_array($field->validation) ? $field->validation : [];
        $this->assertFieldMaxSize($file, $validation, $fieldName, $field->label);

        $allowed = $this->normalizeAllowedTypes($validation['allowedFileTypes'] ?? null);
        if (! $this->matchesAllowedTypes($file, $allowed)) {
            $label = trim((string) $field->label) ?: $fieldName;
            throw ValidationException::withMessages([
                'file' => [__(
                    ':label allows only :types.',
                    ['label' => $label, 'types' => implode(', ', array_map('strtoupper', $allowed))],
                )],
            ]);
        }

        $maxFiles = $this->normalizeMaxFiles($validation['maxFiles'] ?? null);
        $existing = $submission->attachments
            ->filter(static fn ($attachment) => (string) ($attachment->field_name ?? '') === $fieldName)
            ->count();

        if ($existing >= $maxFiles) {
            $label = trim((string) $field->label) ?: $fieldName;
            throw ValidationException::withMessages([
                'file' => [__(
                    ':label allows at most :max file(s).',
                    ['label' => $label, 'max' => $maxFiles],
                )],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeAllowedTypes(mixed $raw): array
    {
        $defaults = ['jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        if (! is_array($raw)) {
            return $defaults;
        }

        $allowed = [];
        foreach ($raw as $item) {
            $type = strtolower(trim((string) $item));
            if (in_array($type, $defaults, true)) {
                $allowed[] = $type;
            }
        }

        return $allowed !== [] ? array_values(array_unique($allowed)) : $defaults;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function assertFieldMaxSize(
        UploadedFile $file,
        array $validation,
        string $fieldName,
        ?string $fieldLabel,
    ): void {
        $maxMb = $validation['maxFileSizeMb'] ?? null;
        if (! is_numeric($maxMb) || (float) $maxMb <= 0) {
            return;
        }

        $maxBytes = (int) round((float) $maxMb * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            $label = trim((string) $fieldLabel) ?: $fieldName;
            throw ValidationException::withMessages([
                'file' => [__(
                    ':label allows files up to :max MB.',
                    ['label' => $label, 'max' => (int) $maxMb],
                )],
            ]);
        }
    }

    private function normalizeMaxFiles(mixed $raw): int
    {
        if (! is_numeric($raw)) {
            return 5;
        }

        return max(1, min(20, (int) $raw));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function matchesAllowedTypes(UploadedFile $file, array $allowed): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        foreach ($allowed as $type) {
            if ($type === 'jpeg' && ($mime === 'image/jpeg' || in_array($ext, ['jpg', 'jpeg'], true))) {
                return true;
            }
            if ($type === 'png' && ($mime === 'image/png' || $ext === 'png')) {
                return true;
            }
            if ($type === 'pdf' && ($mime === 'application/pdf' || $ext === 'pdf')) {
                return true;
            }
            if ($type === 'doc' && ($mime === 'application/msword' || $ext === 'doc')) {
                return true;
            }
            if ($type === 'docx' && (
                $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                || $ext === 'docx'
            )) {
                return true;
            }
            if ($type === 'xls' && ($mime === 'application/vnd.ms-excel' || $ext === 'xls')) {
                return true;
            }
            if ($type === 'xlsx' && (
                $mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                || $ext === 'xlsx'
            )) {
                return true;
            }
            if ($type === 'ppt' && ($mime === 'application/vnd.ms-powerpoint' || $ext === 'ppt')) {
                return true;
            }
            if ($type === 'pptx' && (
                $mime === 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                || $ext === 'pptx'
            )) {
                return true;
            }
        }

        return false;
    }
}
