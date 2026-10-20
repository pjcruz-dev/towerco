<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\Schema;

/**
 * Role Management → AI Assistant. Spatie still decides whether the assistant opens.
 * This policy decides what it may do after that.
 */
final class AssistantRolePolicy
{
    /** @var array<string, string> */
    private const ACTION_CAPABILITY = [
        'create_tenant_user' => 'users_roles',
        'update_user_roles' => 'users_roles',
        'set_user_active' => 'users_roles',
        'create_html_report_from_prompt' => 'build_reports',
        'create_html_report_from_template' => 'build_reports',
        'pin_html_report_to_dashboard' => 'build_reports',
        'cancel_eapproval_submission' => 'workflows',
        'reject_eapproval_submission' => 'workflows',
        'change_ticket_status' => 'workflows',
        'change_it_asset_status' => 'workflows',
        'suggest_document_metadata' => 'ask_lookup',
        'draft_ticket' => 'create_update',
        'draft_e_approval_submission' => 'create_update',
        'update_controlled_document' => 'create_update',
        'update_it_asset' => 'create_update',
        'seed_sample_it_assets' => 'create_update',
    ];

    public function blocksAssistant(TenantUser $user): bool
    {
        $ai = $this->policy($user);
        if (! is_array($ai)) {
            return false;
        }
        if (($ai['preset'] ?? '') === 'none') {
            return true;
        }

        $caps = RoleAccessMatrix::capabilitiesForPreset(
            (string) ($ai['preset'] ?? 'unrestricted'),
            is_array($ai['capabilities'] ?? null) ? $ai['capabilities'] : [],
        );

        return ! in_array(true, $caps, true);
    }

    public function allowsCapability(TenantUser $user, string $capability): bool
    {
        $ai = $this->policy($user);
        if ($ai === null) {
            return true;
        }
        if (($ai['preset'] ?? '') === 'none') {
            return false;
        }
        $caps = RoleAccessMatrix::capabilitiesForPreset(
            (string) ($ai['preset'] ?? 'unrestricted'),
            is_array($ai['capabilities'] ?? null) ? $ai['capabilities'] : [],
        );

        return (bool) ($caps[$capability] ?? false);
    }

    public function allowsAction(TenantUser $user, string $action): bool
    {
        $capability = self::ACTION_CAPABILITY[$action] ?? 'create_update';

        return $this->allowsCapability($user, $capability);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function allowsTool(TenantUser $user, string $tool, array $args = []): bool
    {
        if ($tool !== 'execute_sql') {
            return $this->allowsCapability($user, 'ask_lookup');
        }

        $sql = strtolower(trim((string) ($args['sql'] ?? '')));
        if (preg_match('/^\s*delete\b/', $sql) === 1) {
            return $this->allowsCapability($user, 'delete_records');
        }
        if (preg_match('/^\s*(insert|update|replace)\b/', $sql) === 1) {
            return $this->allowsCapability($user, 'create_update');
        }

        return $this->allowsCapability($user, 'ask_lookup');
    }

    /**
     * Extra lines for the model. Empty when this role is not restricted.
     */
    public function promptConstraint(TenantUser $user): string
    {
        $ai = $this->policy($user);
        if ($ai === null || ($ai['preset'] ?? '') === 'none') {
            return '';
        }
        $denied = [];
        foreach (RoleAccessMatrix::AI_CAPABILITIES as $cap) {
            if (! $this->allowsCapability($user, $cap)) {
                $denied[] = str_replace('_', ' ', $cap);
            }
        }
        $lines = [];
        if ($denied !== []) {
            $lines[] = 'This role forbids: '.implode(', ', $denied).'. Refuse those requests.';
        }
        $instructions = trim((string) ($ai['instructions'] ?? ''));
        if ($instructions !== '') {
            $lines[] = 'Role instructions: '.$instructions;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{preset: string, capabilities?: array<string, bool>, instructions?: string}|null
     */
    private function policy(TenantUser $user): ?array
    {
        if ($user->hasRole(TenantRbacSystemRoles::FULL_ADMIN)) {
            return null;
        }
        if (! Schema::connection('tenant')->hasColumn('roles', 'access_matrix_json')) {
            return null;
        }

        $matrices = [];
        foreach ($user->roles as $role) {
            if ($role instanceof TenantRole) {
                $matrices[] = $role->accessMatrix();
            }
        }
        $ai = RoleAccessMatrix::merge($matrices)['ai'] ?? null;

        return is_array($ai) ? $ai : null;
    }
}
