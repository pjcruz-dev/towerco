<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\PlatformTenantAuditLog;
use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use App\Modules\Platform\Support\TenantChromePalette;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Support\Str;
use RuntimeException;

final class TenantAppearanceService
{
    public function __construct(
        private readonly StructuredAuditLogWriter $auditWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        return $this->present($this->resolveTenant());
    }

    /**
     * @param  array{
     *     primary_preset?: string|null,
     *     primary?: string|null,
     *     skin: string,
     *     layout: string,
     *     content: string
     * }  $data
     * @return array<string, mixed>
     */
    public function update(array $data, TenantUser $actor): array
    {
        $tenant = $this->resolveTenant();
        $existing = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];
        $before = TenantThemeTokensValidator::normalizeChrome(
            is_array($existing['chrome'] ?? null) ? $existing['chrome'] : null,
        );

        $preset = array_key_exists('primary_preset', $data) && is_string($data['primary_preset'])
            ? $data['primary_preset']
            : null;
        $primary = TenantChromePalette::resolvePrimary(
            $preset,
            is_string($data['primary'] ?? null) ? $data['primary'] : null,
        );

        $chrome = TenantThemeTokensValidator::normalizeChrome([
            'saved' => true,
            'primary_preset' => $preset,
            'primary' => $primary,
            'skin' => $data['skin'],
            'layout' => $data['layout'],
            'content' => $data['content'],
        ]);

        $light = is_array($existing['light'] ?? null) ? $existing['light'] : [];
        $dark = is_array($existing['dark'] ?? null) ? $existing['dark'] : [];
        $painted = TenantChromePalette::paint(
            $this->stringMap($light),
            $this->stringMap($dark),
            $primary,
        );

        $existing['version'] = min(999, ((int) ($existing['version'] ?? 0)) + 1);
        $existing['light'] = $painted['light'];
        $existing['dark'] = $painted['dark'];
        $existing['chrome'] = $chrome;
        if (! array_key_exists('logo_url', $existing)) {
            $existing['logo_url'] = null;
        }
        if (! array_key_exists('favicon_url', $existing)) {
            $existing['favicon_url'] = null;
        }

        $tenant->theme_tokens = TenantThemeTokensValidator::validate($existing);
        $tenant->save();

        $this->audit($tenant, $actor, $before, $chrome);

        return $this->present($tenant);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Tenant $tenant): array
    {
        $raw = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : null;

        return TenantThemeTokensValidator::sanitizeForPublic($raw);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Tenant $tenant, TenantUser $actor, array $before, array $after): void
    {
        $recordId = (string) Str::uuid();
        $changes = [
            'chrome' => [
                'from' => $before,
                'to' => $after,
            ],
        ];

        PlatformTenantAuditLog::query()->create([
            'id' => $recordId,
            'tenant_id' => $tenant->id,
            'event_type' => PlatformTenantAuditEventType::TENANT_APPEARANCE_UPDATED,
            'actor_user_id' => null,
            'actor_email' => $actor->email,
            'changes' => $changes,
            'metadata' => [
                'tenant_user_id' => (string) $actor->id,
            ],
            'created_at' => now(),
        ]);

        $this->auditWriter->write('platform.tenant', PlatformTenantAuditEventType::TENANT_APPEARANCE_UPDATED, [
            'record_id' => $recordId,
            'tenant_id' => $tenant->id,
            'actor_email' => $actor->email,
            'changes' => $changes,
        ]);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    private function resolveTenant(): Tenant
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            throw new RuntimeException('Tenant context is required.');
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($tenantKey);
        if ($tenant === null) {
            throw new RuntimeException('Tenant context is required.');
        }

        return $tenant;
    }
}
