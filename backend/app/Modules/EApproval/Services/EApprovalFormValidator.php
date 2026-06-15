<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Illuminate\Validation\ValidationException;

final class EApprovalFormValidator
{
    public function __construct(
        private readonly EApprovalPlanFeaturesService $planFeatures,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> warnings
     */
    public function validate(array $payload, bool $strictPublished = false): array
    {
        $warnings = [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => [__('Form name is required.')]]);
        }

        $fields = $payload['fields'] ?? null;
        if (! is_array($fields) || count($fields) === 0) {
            throw ValidationException::withMessages(['fields' => [__('At least one field is required.')]]);
        }

        $this->planFeatures->assertFormFileFieldsAllowed($fields);

        $names = [];
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages(["fields.{$index}" => [__('Invalid field definition.')]]);
            }
            $fieldName = trim((string) ($field['name'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));
            $type = trim((string) ($field['type'] ?? ''));
            if ($fieldName === '' || $label === '' || $type === '') {
                throw ValidationException::withMessages(["fields.{$index}" => [__('Each field needs type, name, and label.')]]);
            }
            if (isset($names[$fieldName])) {
                throw ValidationException::withMessages(["fields.{$index}.name" => [__('Field names must be unique.')]]);
            }
            $names[$fieldName] = true;
        }

        $steps = $payload['steps'] ?? [];
        if ($strictPublished && is_array($steps) && count($steps) === 0) {
            $warnings[] = 'Published form has no approval workflow steps; submissions will auto-approve.';
        }

        return $warnings;
    }
}
