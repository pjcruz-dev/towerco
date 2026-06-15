<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use Illuminate\Validation\ValidationException;

final class EApprovalSubmissionValuesValidator
{
    public function __construct(
        private readonly EApprovalFieldVisibilityEvaluator $visibility,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function validate(EApprovalForm $form, array $values, bool $requireRequired = true): void
    {
        $form->loadMissing('fields');
        $errors = [];

        foreach ($form->fields as $field) {
            if ($this->isStructuralField($field)) {
                continue;
            }

            if (! $this->visibility->isVisible($field, $values)) {
                continue;
            }

            $name = (string) $field->name;
            $raw = $values[$name] ?? $values[(string) $field->id] ?? null;
            $value = $this->normalizeValue($raw);
            $validation = is_array($field->validation) ? $field->validation : [];
            $label = trim((string) $field->label) ?: $name;

            if ($requireRequired && ($validation['required'] ?? false) === true && $value === '') {
                $errors["values.{$name}"] = [__(':label is required.', ['label' => $label])];

                continue;
            }

            if ($value === '') {
                continue;
            }

            $maxLength = isset($validation['max_length']) ? (int) $validation['max_length'] : 0;
            if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
                $errors["values.{$name}"] = [__(':label must be at most :max characters.', ['label' => $label, 'max' => $maxLength])];
            }

            $type = (string) $field->type;
            if ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors["values.{$name}"] = [__(':label must be a valid email address.', ['label' => $label])];
            }

            if ($type === 'phone' && ! $this->isValidPhone($value)) {
                $errors["values.{$name}"] = [__(':label must be a valid phone number.', ['label' => $label])];
            }

            if ($type === 'url' && ! $this->isValidUrl($value)) {
                $errors["values.{$name}"] = [__(':label must be a valid URL.', ['label' => $label])];
            }

            if ($type === 'rating' && ! $this->isValidRating($field, $value)) {
                $errors["values.{$name}"] = [__(':label must be a valid rating.', ['label' => $label])];
            }

            if ($type === 'location' && ! $this->isValidLocation($value)) {
                $errors["values.{$name}"] = [__(':label must include valid coordinates.', ['label' => $label])];
            }

            if ($type === 'tags' && ! $this->isValidTags($value, $requireRequired && ($validation['required'] ?? false) === true)) {
                $errors["values.{$name}"] = [__(':label must include at least one tag.', ['label' => $label])];
            }

            if ($type === 'signature' && ! $this->isValidSignature($value)) {
                $errors["values.{$name}"] = [__(':label must include a signature.', ['label' => $label])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isStructuralField(EApprovalFormField $field): bool
    {
        return in_array((string) $field->type, ['section', 'divider'], true);
    }

    private function normalizeValue(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        if (is_scalar($raw)) {
            return trim((string) $raw);
        }

        return trim(json_encode($raw, JSON_THROW_ON_ERROR) ?: '');
    }

    private function isValidPhone(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    private function isValidUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        return filter_var('https://'.$value, FILTER_VALIDATE_URL) !== false;
    }

    private function isValidRating(EApprovalFormField $field, string $value): bool
    {
        if (! ctype_digit($value)) {
            return false;
        }

        $rating = (int) $value;
        $options = is_array($field->options) ? $field->options : [];
        $max = max(1, min(10, (int) ($options['max_stars'] ?? 5)));

        return $rating >= 1 && $rating <= $max;
    }

    private function isValidLocation(string $value): bool
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded['lat'], $decoded['lng'])) {
            return is_numeric($decoded['lat']) && is_numeric($decoded['lng']);
        }

        return (bool) preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value);
    }

    private function isValidTags(string $value, bool $required): bool
    {
        if ($value === '') {
            return ! $required;
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            $count = is_array($decoded)
                ? count(array_filter($decoded, static fn ($t) => trim((string) $t) !== ''))
                : 0;

            return $count > 0;
        }

        return count(array_filter(array_map('trim', explode(',', $value)))) > 0;
    }

    private function isValidSignature(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, 'data:image/')) {
            return strlen($trimmed) <= 500000;
        }

        return strlen($trimmed) <= 5000;
    }
}
