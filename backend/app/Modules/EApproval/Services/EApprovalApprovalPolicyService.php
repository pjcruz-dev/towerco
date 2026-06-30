<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Data\EApprovalApprovalPolicyDefaults;
use App\Modules\EApproval\Models\EApprovalApprovalPolicy;
use App\Modules\EApproval\Models\EApprovalApprovalPolicyVersion;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalApprovalPolicyService
{
    public function publishedVersion(): ?EApprovalApprovalPolicyVersion
    {
        $policy = $this->ensurePolicy();

        return $policy->versions()
            ->where('status', 'published')
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForAdmin(): array
    {
        $policy = $this->ensurePolicy();
        $draft = $this->draftVersion($policy);
        $published = $this->publishedVersion();

        return [
            'policy' => [
                'id' => (string) $policy->id,
                'key' => $policy->key,
                'name' => $policy->name,
                'description' => $policy->description,
            ],
            'published_version' => $published ? $this->versionPayload($published) : null,
            'draft_version' => $draft ? $this->versionPayload($draft) : null,
            'defaults' => EApprovalApprovalPolicyDefaults::config(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateDraft(array $config): array
    {
        $policy = $this->ensurePolicy();
        $draft = $this->draftVersion($policy) ?? $this->createDraftVersion($policy);
        $normalized = $this->normalizeConfig($config);

        $draft->config_json = json_encode($normalized, JSON_THROW_ON_ERROR);
        $draft->save();

        return $this->snapshotForAdmin();
    }

    public function publish(TenantUser $publisher): array
    {
        $policy = $this->ensurePolicy();
        $draft = $this->draftVersion($policy);
        if (! $draft instanceof EApprovalApprovalPolicyVersion) {
            throw ValidationException::withMessages([
                'approval_policy' => [__('No draft approval policy to publish.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($policy, $draft, $publisher): array {
            $policy->versions()
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            $nextNumber = ((int) $policy->versions()->max('version_number')) + 1;
            $published = EApprovalApprovalPolicyVersion::query()->create([
                'id' => (string) Str::uuid(),
                'policy_id' => $policy->id,
                'version_number' => $nextNumber,
                'status' => 'published',
                'config_json' => $draft->config_json,
                'published_at' => now(),
                'published_by' => $publisher->id,
            ]);

            $draft->delete();

            return $this->snapshotForAdmin() + [
                'published_version_id' => (string) $published->id,
            ];
        });
    }

    private function ensurePolicy(): EApprovalApprovalPolicy
    {
        $policy = EApprovalApprovalPolicy::query()->where('key', EApprovalApprovalPolicyDefaults::POLICY_KEY)->first();
        if ($policy instanceof EApprovalApprovalPolicy) {
            if ($policy->versions()->count() === 0) {
                $this->seedInitialPublishedVersion($policy);
            }

            return $policy;
        }

        return DB::connection('tenant')->transaction(function (): EApprovalApprovalPolicy {
            $policy = EApprovalApprovalPolicy::query()->create([
                'id' => (string) Str::uuid(),
                'key' => EApprovalApprovalPolicyDefaults::POLICY_KEY,
                'name' => 'Tenant approval policy',
                'description' => 'Dynamic DOA matrix for procurement and finance forms.',
            ]);

            $this->seedInitialPublishedVersion($policy);

            return $policy;
        });
    }

    private function seedInitialPublishedVersion(EApprovalApprovalPolicy $policy): void
    {
        EApprovalApprovalPolicyVersion::query()->create([
            'id' => (string) Str::uuid(),
            'policy_id' => $policy->id,
            'version_number' => 1,
            'status' => 'published',
            'config_json' => json_encode(EApprovalApprovalPolicyDefaults::config(), JSON_THROW_ON_ERROR),
            'published_at' => now(),
        ]);
    }

    private function draftVersion(EApprovalApprovalPolicy $policy): ?EApprovalApprovalPolicyVersion
    {
        return $policy->versions()->where('status', 'draft')->orderByDesc('version_number')->first();
    }

    private function createDraftVersion(EApprovalApprovalPolicy $policy): EApprovalApprovalPolicyVersion
    {
        $published = $this->publishedVersion();
        $baseConfig = $published?->config() ?? EApprovalApprovalPolicyDefaults::config();

        return EApprovalApprovalPolicyVersion::query()->create([
            'id' => (string) Str::uuid(),
            'policy_id' => $policy->id,
            'version_number' => (int) $policy->versions()->max('version_number') + 1,
            'status' => 'draft',
            'config_json' => json_encode($baseConfig, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(EApprovalApprovalPolicyVersion $version): array
    {
        return [
            'id' => (string) $version->id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'label' => $version->label(),
            'config' => $version->config(),
            'published_at' => $version->published_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $profiles = is_array($config['workflow_profiles'] ?? null) ? $config['workflow_profiles'] : [];
        $rules = is_array($config['rules'] ?? null) ? array_values($config['rules']) : [];
        $defaults = is_array($config['default_profiles'] ?? null) ? $config['default_profiles'] : [];

        if ($profiles === []) {
            throw ValidationException::withMessages([
                'workflow_profiles' => [__('At least one workflow profile is required.')],
            ]);
        }

        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $profile = trim((string) ($rule['workflow_profile'] ?? ''));
            if ($profile === '' || ! array_key_exists($profile, $profiles)) {
                throw ValidationException::withMessages([
                    "rules.{$index}.workflow_profile" => [__('Workflow profile is invalid.')],
                ]);
            }
        }

        return [
            'currency' => trim((string) ($config['currency'] ?? 'PHP')) ?: 'PHP',
            'workflow_profiles' => $profiles,
            'rules' => $rules,
            'default_profiles' => $defaults,
        ];
    }
}
