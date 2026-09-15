<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

/**
 * Deterministic heuristic: when the user is asking to create/draft something,
 * return an allowlisted action name. Never invent action keys.
 */
final class AssistantActionRouter
{
    /**
     * @return array{action: string, args: array<string, mixed>}|null
     */
    public function match(string $question, ?string $moduleContext = null): ?array
    {
        if (! (bool) config('ai_assistant.actions.enabled', true)) {
            return null;
        }

        $q = mb_strtolower(trim($question));

        if ($this->matches($q, [
            'create a ticket',
            'create ticket',
            'open a ticket',
            'open ticket',
            'raise a ticket',
            'file a ticket',
            'draft a ticket',
            'draft ticket',
            'please create ticket',
        ]) || ($moduleContext === 'ticketing' && $this->matches($q, ['create ticket', 'open ticket', 'new ticket']))) {
            return ['action' => 'draft_ticket', 'args' => []];
        }

        if ($this->matches($q, [
            'draft e-approval',
            'draft e approval',
            'create e-approval draft',
            'draft approval submission',
            'start e-approval',
        ]) || ($moduleContext === 'e_approval' && $this->matches($q, ['draft submission', 'create draft']))) {
            return ['action' => 'draft_e_approval_submission', 'args' => []];
        }

        if ($this->matches($q, [
            'pin report to dashboard',
            'pin html report',
            'add report to dashboard',
            'pin to home dashboard',
        ])) {
            return ['action' => 'pin_html_report_to_dashboard', 'args' => []];
        }

        // Team & Access role changes — before report heuristics (email + "role" must not become a dashboard).
        if (
            preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $question) === 1
            && (
                preg_match('/\b(role|roles|viewer|manager|tenant_admin|administrator|assign|permission)\b/u', $q) === 1
                || preg_match('/\b(change|set|make|update|remove|revoke)\b.{0,40}\b(role|roles|viewer)\b/u', $q) === 1
            )
        ) {
            return ['action' => 'update_user_roles', 'args' => []];
        }

        if ($this->matches($q, [
            'change role',
            'change the role',
            'set role',
            'set the role',
            'assign role',
            'assign roles',
            'update role',
            'update roles',
            'make viewer',
            'viewer only',
            'role to viewer',
        ]) || (
            $moduleContext === 'team_access'
            && preg_match('/\b(role|roles|viewer|assign)\b/u', $q) === 1
        )) {
            return ['action' => 'update_user_roles', 'args' => []];
        }

        if ($this->matches($q, [
            'build a report',
            'build report',
            'create a report',
            'create report',
            'generate a report',
            'generate report',
            'make a report',
            'make report',
            'create html report',
            'build html report',
            'build a dashboard',
            'build dashboard',
            'create a dashboard',
            'create dashboard',
            'generate a dashboard',
            'generate dashboard',
            'new dashboard',
            'make a dashboard',
            'make dashboard',
            'report builder',
            'bar chart of',
            'pie chart of',
            'general ledger report',
            'gl report',
            'gl dashboard',
            'dashboard with filter',
            'report with filter',
            'tower sites',
            'with charts',
            'with chart',
        ]) || (
            (
                str_contains($q, 'dashboard')
                || str_contains($q, 'report')
            )
            && $this->matches($q, ['generate', 'create', 'build', 'make', 'new'])
        ) || (
            in_array($moduleContext, ['dynamic_entities', 'reporting', 'html_reports', 'team_access'], true)
            && $this->matches($q, ['build it', 'save report', 'create report', 'make a report', 'create dashboard', 'generate dashboard'])
        )) {
            return [
                'action' => 'create_html_report_from_prompt',
                'args' => $this->reportActionArgs($q, $moduleContext),
            ];
        }

        return null;
    }

    /**
     * @return array{entity_hints?: list<string>, module_context?: string}
     */
    private function reportActionArgs(string $question, ?string $moduleContext): array
    {
        $args = [];
        if (is_string($moduleContext) && $moduleContext !== '') {
            $args['module_context'] = $moduleContext;
        }

        $hints = [];
        if (
            str_contains($question, 'team & access')
            || str_contains($question, 'team and access')
            || str_contains($question, 'workspace users')
            || str_contains($question, 'all users')
            || preg_match('/\busers?\b/', $question) === 1
            || $moduleContext === 'team_access'
        ) {
            $hints = ['users', 'users_system', 'tenant_users'];
        } elseif (str_contains($question, 'general ledger') || str_contains($question, 'gl report') || str_contains($question, 'gl dashboard')) {
            $hints = ['general_ledger', 'gl'];
        } elseif (str_contains($question, 'chart of accounts') || str_contains($question, 'coa')) {
            $hints = ['chart_of_accounts'];
        } elseif (
            str_contains($question, 'tower sites')
            || str_contains($question, 'tower site')
            || str_contains($question, 'sites inventory')
        ) {
            $hints = ['tower_sites', 'sites', 'site'];
        }

        if ($hints !== []) {
            $args['entity_hints'] = $hints;
        }

        return $args;
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
