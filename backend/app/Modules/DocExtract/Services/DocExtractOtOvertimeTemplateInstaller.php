<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Models\DocExtractTemplate;
use App\Modules\DocExtract\Support\DocExtractOtOvertimeTemplateCatalog;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

final class DocExtractOtOvertimeTemplateInstaller
{
    public function __construct(
        private readonly DocExtractTemplateService $templates,
    ) {}

    /**
     * Create or update the published OT overtime template for the current tenant.
     *
     * @return array{template: DocExtractTemplate, created: bool}
     */
    public function ensure(?TenantUser $actor = null): array
    {
        $definition = DocExtractOtOvertimeTemplateCatalog::definition();
        $existing = DocExtractTemplate::query()
            ->where('name', DocExtractOtOvertimeTemplateCatalog::NAME)
            ->first();

        if ($existing instanceof DocExtractTemplate) {
            $updated = $this->templates->update($existing, [
                'description' => $definition['description'],
                'status' => DocExtractTemplateStatus::PUBLISHED,
                'fields' => $definition['fields'],
            ], $actor);

            return ['template' => $updated, 'created' => false];
        }

        if ($actor instanceof TenantUser) {
            $created = $this->templates->create($definition, $actor);

            return ['template' => $created, 'created' => true];
        }

        $created = DocExtractTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $definition['name'],
            'description' => $definition['description'],
            'fields' => $definition['fields'],
            'status' => DocExtractTemplateStatus::PUBLISHED,
            'created_by_id' => null,
        ]);

        return ['template' => $created->fresh() ?? $created, 'created' => true];
    }
}
