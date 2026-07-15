<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalFormService
{
    private const SORTABLE = [
        'name',
        'status',
        'category',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly EApprovalFormValidator $validator,
        private readonly EApprovalAuditLogger $audit,
        private readonly FormPublishService $publish,
        private readonly EApprovalFormRevisionService $revisions,
        private readonly EApprovalFormSyncService $sync,
        private readonly EApprovalFormPublishGuard $publishGuard,
    ) {}

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        string $search,
        bool $manageAll,
        ?string $statusFilter = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = EApprovalForm::query();

        if ($statusFilter === 'published') {
            $query->where('status', 'published');
        } elseif ($statusFilter === 'draft') {
            $query->where('status', 'draft');
        } elseif (! $manageAll) {
            $query->where(static function ($q): void {
                $q->where('status', 'published')->orWhereNull('status');
            })->where(static function ($q): void {
                $q->where('accepts_new_submissions', true)->orWhereNull('accepts_new_submissions');
            });
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'created_at:desc'),
            self::SORTABLE,
            'created_at',
            'desc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{form: EApprovalForm, warnings: list<string>}
     */
    public function create(array $payload, TenantUser $actor): array
    {
        $status = in_array($payload['status'] ?? 'draft', ['draft', 'published'], true)
            ? (string) $payload['status']
            : 'draft';
        $warnings = $this->validator->validate($payload, $status === 'published');

        if ($status === 'published') {
            EApprovalFormWorkspaceSupport::assertUniqueSlug(
                is_array($payload['metadata_json'] ?? null) ? $payload['metadata_json'] : null,
            );
            EApprovalFormWorkspaceSupport::assertValidWorkspaceMetadata(
                is_array($payload['metadata_json'] ?? null) ? $payload['metadata_json'] : null,
            );
        }

        return DB::connection('tenant')->transaction(function () use ($payload, $actor, $status, $warnings) {
            $form = EApprovalForm::query()->create([
                'id' => (string) Str::uuid(),
                'name' => trim((string) $payload['name']),
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? 'general',
                'metadata_json' => is_array($payload['metadata_json'] ?? null) ? $payload['metadata_json'] : null,
                'restricted_to' => $payload['restricted_to'] ?? null,
                'status' => $status,
                'accepts_new_submissions' => array_key_exists('accepts_new_submissions', $payload)
                    ? (bool) $payload['accepts_new_submissions']
                    : true,
                'schema_version' => max(1, (int) ($payload['schema_version'] ?? 1)),
                'owner_code' => $payload['owner_code'] ?? 'GEN',
                'doc_type_code' => $payload['doc_type_code'] ?? 'F',
                'doc_no_custom_enabled' => (bool) ($payload['doc_no_custom_enabled'] ?? false),
                'doc_no_template' => $payload['doc_no_template'] ?? null,
                'doc_no_seq_start' => $payload['doc_no_seq_start'] ?? null,
                'doc_no_seq_start_rules' => is_array($payload['doc_no_seq_start_rules'] ?? null)
                    ? $payload['doc_no_seq_start_rules']
                    : null,
                'brand_logo_url' => $payload['brand_logo_url'] ?? null,
                'brand_primary_color' => $payload['brand_primary_color'] ?? null,
                'related_form_ids' => $payload['related_form_ids'] ?? null,
            ]);

            $this->sync->sync($form, $payload);

            if ($status === 'published') {
                $this->publish->publish($form, $actor);
            }

            $this->audit->log('form_created', $form->id, $form->name, $actor);

            return ['form' => $form->fresh(['fields', 'workflowTemplate.steps']), 'warnings' => $warnings];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{form: EApprovalForm, warnings: list<string>}
     */
    public function update(EApprovalForm $form, array $payload, TenantUser $actor, bool $confirmFormUpgrade = false): array
    {
        $status = $payload['status'] ?? $form->status;
        $warnings = $this->validator->validate($payload, $status === 'published');
        $warnings = array_merge($warnings, $this->publishGuard->warningsFor($form, $payload));

        $this->publishGuard->assertCanApply(
            $form,
            $payload,
            $confirmFormUpgrade,
            $status === 'published' || $form->status === 'published',
        );

        $effectiveStatus = in_array($status, ['draft', 'published'], true) ? $status : $form->status;
        if ($effectiveStatus === 'published') {
            $metadata = is_array($payload['metadata_json'] ?? null)
                ? $payload['metadata_json']
                : (is_array($form->metadata_json) ? $form->metadata_json : null);
            EApprovalFormWorkspaceSupport::assertUniqueSlug($metadata, (string) $form->id);
            EApprovalFormWorkspaceSupport::assertValidWorkspaceMetadata($metadata, (string) $form->id);
        }

        return DB::connection('tenant')->transaction(function () use ($form, $payload, $actor, $status, $warnings) {
            $form->fill([
                'name' => trim((string) ($payload['name'] ?? $form->name)),
                'description' => $payload['description'] ?? $form->description,
                'category' => $payload['category'] ?? $form->category,
                'metadata_json' => is_array($payload['metadata_json'] ?? null) ? $payload['metadata_json'] : $form->metadata_json,
                'restricted_to' => $payload['restricted_to'] ?? $form->restricted_to,
                'status' => in_array($status, ['draft', 'published'], true) ? $status : $form->status,
                'accepts_new_submissions' => array_key_exists('accepts_new_submissions', $payload)
                    ? (bool) $payload['accepts_new_submissions']
                    : $form->accepts_new_submissions,
                'schema_version' => max(1, (int) ($payload['schema_version'] ?? $form->schema_version)),
                'owner_code' => $payload['owner_code'] ?? $form->owner_code,
                'doc_type_code' => $payload['doc_type_code'] ?? $form->doc_type_code,
                'doc_no_custom_enabled' => (bool) ($payload['doc_no_custom_enabled'] ?? $form->doc_no_custom_enabled),
                'doc_no_template' => $payload['doc_no_template'] ?? $form->doc_no_template,
                'doc_no_seq_start' => $payload['doc_no_seq_start'] ?? $form->doc_no_seq_start,
                'doc_no_seq_start_rules' => is_array($payload['doc_no_seq_start_rules'] ?? null)
                    ? $payload['doc_no_seq_start_rules']
                    : $form->doc_no_seq_start_rules,
                'brand_logo_url' => $payload['brand_logo_url'] ?? $form->brand_logo_url,
                'brand_primary_color' => $payload['brand_primary_color'] ?? $form->brand_primary_color,
                'related_form_ids' => $payload['related_form_ids'] ?? $form->related_form_ids,
            ]);
            $form->save();

            $this->sync->sync($form, $payload);

            $form->refresh();
            $this->revisions->record($form->fresh(['fields', 'workflowTemplate.steps']), $actor, 'saved');

            if ($form->status === 'published') {
                $this->publish->publish($form->fresh(['fields', 'workflowTemplate.steps']), $actor);
            }

            $this->audit->log('form_updated', $form->id, $form->name, $actor);

            return ['form' => $form->fresh(['fields', 'workflowTemplate.steps']), 'warnings' => $warnings];
        });
    }

    /**
     * @return array{form: EApprovalForm, warnings: list<string>}
     */
    public function restoreFromRevision(EApprovalForm $form, int $revision, TenantUser $actor): array
    {
        $snapshot = $this->revisions->snapshotFor($form, $revision);
        if ($snapshot === null) {
            throw ValidationException::withMessages([
                'revision' => [__('Revision not found or has no snapshot.')],
            ]);
        }

        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        if ($fields === []) {
            throw ValidationException::withMessages([
                'revision' => [__('Revision snapshot has no fields.')],
            ]);
        }

        $status = in_array($snapshot['status'] ?? 'draft', ['draft', 'published'], true)
            ? (string) $snapshot['status']
            : 'draft';

        $currentMeta = is_array($form->metadata_json) ? $form->metadata_json : [];
        $snapshotMeta = is_array($snapshot['metadata_json'] ?? null) ? $snapshot['metadata_json'] : [];
        if (isset($currentMeta['revisions'])) {
            $snapshotMeta['revisions'] = $currentMeta['revisions'];
        }

        $payload = [
            'name' => (string) ($snapshot['name'] ?? $form->name),
            'description' => $snapshot['description'] ?? null,
            'category' => $snapshot['category'] ?? $form->category,
            'metadata_json' => $snapshotMeta !== [] ? $snapshotMeta : $form->metadata_json,
            'restricted_to' => $snapshot['restricted_to'] ?? $form->restricted_to,
            'status' => $status,
            'schema_version' => max(1, (int) ($snapshot['schema_version'] ?? $form->schema_version)),
            'owner_code' => $snapshot['owner_code'] ?? $form->owner_code,
            'doc_type_code' => $snapshot['doc_type_code'] ?? $form->doc_type_code,
            'doc_no_custom_enabled' => (bool) ($snapshot['doc_no_custom_enabled'] ?? $form->doc_no_custom_enabled),
            'doc_no_template' => $snapshot['doc_no_template'] ?? $form->doc_no_template,
            'doc_no_seq_start' => $snapshot['doc_no_seq_start'] ?? $form->doc_no_seq_start,
            'doc_no_seq_start_rules' => $snapshot['doc_no_seq_start_rules'] ?? $form->doc_no_seq_start_rules,
            'brand_logo_url' => $snapshot['brand_logo_url'] ?? $form->brand_logo_url,
            'brand_primary_color' => $snapshot['brand_primary_color'] ?? $form->brand_primary_color,
            'related_form_ids' => $snapshot['related_form_ids'] ?? $form->related_form_ids,
            'fields' => $fields,
            'steps' => is_array($snapshot['steps'] ?? null) ? $snapshot['steps'] : [],
        ];

        $result = $this->update($form, $payload, $actor, true);
        $this->audit->log('form_revision_restored', $form->id, $form->name.' (rev '.$revision.')', $actor);

        return $result;
    }

    public function destroy(EApprovalForm $form, TenantUser $actor): void
    {
        if ($form->submissions()->exists()) {
            throw ValidationException::withMessages([
                'form' => [__('Cannot delete a form that has submissions.')],
            ]);
        }

        $form->delete();
        $this->audit->log('form_deleted', $form->id, $form->name, $actor);
    }

}
