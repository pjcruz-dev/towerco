<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Billing\Services\TenantPlanEntitlementsService;

final class EApprovalPlanFeaturesService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array{plan_tier: string, file_uploads: bool, max_file_fields: int|null}
     */
    public function snapshot(?string $tenantId = null): array
    {
        return $this->entitlements->eApprovalFeatures($tenantId);
    }

    public function fileUploadsAllowed(): bool
    {
        return $this->snapshot()['file_uploads'];
    }

    public function maxFileFields(): ?int
    {
        $max = $this->snapshot()['max_file_fields'];

        return $max;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function assertFormFileFieldsAllowed(array $fields): void
    {
        if ($this->fileUploadsAllowed()) {
            $max = $this->maxFileFields();
            if ($max === null) {
                return;
            }

            $fileCount = 0;
            foreach ($fields as $field) {
                if (is_array($field) && ($field['type'] ?? '') === 'file') {
                    $fileCount++;
                }
            }

            if ($fileCount > $max) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fields' => [__('Your plan allows at most :max file upload field(s). Upgrade to add more.', ['max' => $max])],
                ]);
            }

            return;
        }

        foreach ($fields as $index => $field) {
            if (is_array($field) && ($field['type'] ?? '') === 'file') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "fields.{$index}" => [__('File upload fields require a Professional or Enterprise plan.')],
                ]);
            }
        }
    }

    public function assertCanUploadAttachment(): void
    {
        if (! $this->fileUploadsAllowed()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => [__('File uploads are not included on your current plan.')],
            ]);
        }
    }

}
