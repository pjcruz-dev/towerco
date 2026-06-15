<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EApprovalForm extends Model
{
    use HasUuids;

    protected $table = 'e_approval_forms';

    protected $fillable = [
        'name',
        'description',
        'category',
        'metadata_json',
        'restricted_to',
        'status',
        'accepts_new_submissions',
        'schema_version',
        'published_snapshot',
        'owner_code',
        'doc_type_code',
        'doc_no_custom_enabled',
        'doc_no_template',
        'doc_no_seq_start',
        'doc_no_seq_start_rules',
        'brand_logo_url',
        'brand_primary_color',
        'related_form_ids',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'doc_no_custom_enabled' => 'boolean',
            'accepts_new_submissions' => 'boolean',
            'doc_no_seq_start_rules' => 'array',
            'schema_version' => 'integer',
        ];
    }

    /**
     * Legacy imports send `related_form_ids` as a JSON array; the column is text.
     *
     * @param  array<int, string>|string|null  $value
     */
    public function setRelatedFormIdsAttribute(array|string|null $value): void
    {
        if ($value === null) {
            $this->attributes['related_form_ids'] = null;

            return;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $this->attributes['related_form_ids'] = $trimmed === '' ? null : $trimmed;

            return;
        }

        if ($value === []) {
            $this->attributes['related_form_ids'] = null;

            return;
        }

        $this->attributes['related_form_ids'] = json_encode(array_values($value), JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>|null
     */
    public function getRelatedFormIdsAttribute(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_filter($decoded, is_string(...)));
    }

    /** @return HasMany<EApprovalFormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(EApprovalFormField::class, 'form_id')->orderBy('step_order');
    }

    /** @return HasOne<EApprovalWorkflowTemplate, $this> */
    public function workflowTemplate(): HasOne
    {
        return $this->hasOne(EApprovalWorkflowTemplate::class, 'form_id');
    }

    /** @return HasMany<EApprovalSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(EApprovalSubmission::class, 'form_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toListRow(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'schema_version' => $this->schema_version,
            'owner_code' => $this->owner_code,
            'doc_type_code' => $this->doc_type_code,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(): array
    {
        $this->loadMissing(['fields', 'workflowTemplate.steps']);

        $submissionsCount = (int) ($this->submissions_count ?? $this->submissions()->count());
        $pendingSubmissionsCount = (int) ($this->pending_submissions_count ?? $this->submissions()
            ->whereIn('status', EApprovalSubmissionStatus::open())
            ->count());

        $steps = $this->workflowTemplate?->steps->map(static fn (EApprovalWorkflowStep $s) => [
            'id' => (string) $s->id,
            'step_order' => $s->step_order,
            'type' => $s->approver_type,
            'approverId' => $s->approver_id,
            'condition' => $s->condition ?? new \stdClass,
        ])->values()->all() ?? [];

        $metadata = is_array($this->metadata_json) ? $this->metadata_json : [];
        $revisions = is_array($metadata['revisions'] ?? null) ? $metadata['revisions'] : [];

        return array_merge($this->toListRow(), [
            'submissions_count' => $submissionsCount,
            'pending_submissions_count' => $pendingSubmissionsCount,
            'accepts_new_submissions' => $this->accepts_new_submissions !== false,
            'revisions' => $revisions,
            'metadata_json' => $this->metadata_json,
            'restricted_to' => $this->restricted_to,
            'published_snapshot' => $this->published_snapshot,
            'doc_no_custom_enabled' => $this->doc_no_custom_enabled,
            'doc_no_template' => $this->doc_no_template,
            'doc_no_seq_start' => $this->doc_no_seq_start,
            'doc_no_seq_start_rules' => $this->doc_no_seq_start_rules,
            'brand_logo_url' => $this->brand_logo_url,
            'brand_primary_color' => $this->brand_primary_color,
            'related_form_ids' => $this->related_form_ids,
            'fields' => $this->fields->map(static fn (EApprovalFormField $f) => [
                'id' => (string) $f->id,
                'type' => $f->type,
                'name' => $f->name,
                'label' => $f->label,
                'semantic_type' => $f->semantic_type,
                'behavior' => $f->behavior,
                'formula' => $f->formula,
                'validation' => $f->validation,
                'options' => $f->options,
                'step_order' => $f->step_order,
            ])->values()->all(),
            'steps' => $steps,
        ]);
    }
}
