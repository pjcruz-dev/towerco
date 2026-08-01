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
        ?array $metadata = null,
    ): void {
        $this->files->assertUploadAllowed($file);

        if ($fieldName === null || trim($fieldName) === '') {
            return;
        }

        $submission->loadMissing(['form.fields', 'attachments']);
        $field = $submission->form?->fields
            ->first(static fn (EApprovalFormField $candidate) => (string) $candidate->name === $fieldName);

        $type = $field !== null ? (string) $field->type : '';
        if ($field === null || ! in_array($type, ['file', 'camera'], true)) {
            throw ValidationException::withMessages([
                'field_name' => [__('Uploaded file does not match a file or camera field on this form.')],
            ]);
        }

        $validation = is_array($field->validation) ? $field->validation : [];
        $options = is_array($field->options) ? $field->options : [];
        $this->assertFieldMaxSize($file, $validation, $fieldName, $field->label);
        $this->assertFieldMinSize($file, $validation, $fieldName, $field->label);

        if ($type === 'camera') {
            if (! $this->isImageUpload($file)) {
                $label = trim((string) $field->label) ?: $fieldName;
                throw ValidationException::withMessages([
                    'file' => [__(':label accepts image files only (JPEG, PNG, WebP, GIF).', ['label' => $label])],
                ]);
            }
            $this->assertCameraMetadata($metadata, $options, $fieldName, $field->label);
            $maxFiles = $this->normalizeCameraMax($options['max'] ?? $validation['maxFiles'] ?? null);
        } else {
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
        }

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
     * Normalize and return validated camera metadata for persistence.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>|null
     */
    public function normalizeMetadata(?array $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $out = [];

        if (array_key_exists('lat', $raw) && is_numeric($raw['lat'])) {
            $lat = (float) $raw['lat'];
            if ($lat >= -90 && $lat <= 90) {
                $out['lat'] = $lat;
            }
        }
        if (array_key_exists('lng', $raw) && is_numeric($raw['lng'])) {
            $lng = (float) $raw['lng'];
            if ($lng >= -180 && $lng <= 180) {
                $out['lng'] = $lng;
            }
        }
        if (isset($raw['captured_at']) && is_string($raw['captured_at']) && trim($raw['captured_at']) !== '') {
            $out['captured_at'] = mb_substr(trim($raw['captured_at']), 0, 64);
        }
        if (isset($raw['caption']) && is_string($raw['caption'])) {
            $caption = trim($raw['caption']);
            if ($caption !== '') {
                $out['caption'] = mb_substr($caption, 0, 500);
            }
        }
        if (isset($raw['slot']) && is_string($raw['slot'])) {
            $slot = trim($raw['slot']);
            if ($slot !== '') {
                $out['slot'] = mb_substr($slot, 0, 120);
            }
        }

        return $out !== [] ? $out : null;
    }

    private function isImageUpload(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    private function assertCameraMetadata(
        ?array $metadata,
        array $options,
        string $fieldName,
        ?string $fieldLabel,
    ): void {
        $label = trim((string) $fieldLabel) ?: $fieldName;
        $slots = $this->normalizeSlots($options['slots'] ?? null);

        if ($slots !== [] && $metadata !== null) {
            $slot = isset($metadata['slot']) ? trim((string) $metadata['slot']) : '';
            if ($slot !== '' && ! in_array($slot, $slots, true)) {
                throw ValidationException::withMessages([
                    'metadata.slot' => [__(':label slot must be one of the configured capture slots.', ['label' => $label])],
                ]);
            }
        }

        if ($metadata === null) {
            return;
        }

        if (array_key_exists('lat', $metadata) && $metadata['lat'] !== null && $metadata['lat'] !== '') {
            if (! is_numeric($metadata['lat']) || (float) $metadata['lat'] < -90 || (float) $metadata['lat'] > 90) {
                throw ValidationException::withMessages([
                    'metadata.lat' => [__('Latitude must be a number between -90 and 90.')],
                ]);
            }
        }
        if (array_key_exists('lng', $metadata) && $metadata['lng'] !== null && $metadata['lng'] !== '') {
            if (! is_numeric($metadata['lng']) || (float) $metadata['lng'] < -180 || (float) $metadata['lng'] > 180) {
                throw ValidationException::withMessages([
                    'metadata.lng' => [__('Longitude must be a number between -180 and 180.')],
                ]);
            }
        }
        if (isset($metadata['caption']) && is_string($metadata['caption']) && mb_strlen($metadata['caption']) > 500) {
            throw ValidationException::withMessages([
                'metadata.caption' => [__('Photo caption must be at most 500 characters.')],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeSlots(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $slots = [];
        foreach ($raw as $item) {
            $slot = trim((string) $item);
            if ($slot !== '') {
                $slots[] = mb_substr($slot, 0, 120);
            }
        }

        return array_values(array_unique($slots));
    }

    private function normalizeCameraMax(mixed $raw): int
    {
        if (! is_numeric($raw)) {
            return 20;
        }

        return max(1, min(50, (int) $raw));
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
    private function assertFieldMinSize(
        UploadedFile $file,
        array $validation,
        string $fieldName,
        ?string $fieldLabel,
    ): void {
        $minKb = $validation['minFileSizeKb'] ?? null;
        if (! is_numeric($minKb) || (int) $minKb <= 0) {
            return;
        }

        $minBytes = (int) $minKb * 1024;
        if ($file->getSize() < $minBytes) {
            $label = trim((string) $fieldLabel) ?: $fieldName;
            throw ValidationException::withMessages([
                'file' => [__(
                    ':label requires files of at least :min KB.',
                    ['label' => $label, 'min' => (int) $minKb],
                )],
            ]);
        }
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
