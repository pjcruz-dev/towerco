<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\DTOs\ToolPlan;

/**
 * Stage-2 planner: runs only when heuristics produced no tool calls.
 * Resolves follow-ups from conversation history and module context using the
 * allowlisted tool catalog — never invents tool names.
 */
final class AssistantToolFallbackPlanner
{
    public function __construct(
        private readonly AssistantToolRegistry $registry,
    ) {}

    /**
     * @param  list<ConversationTurn>  $history
     */
    public function plan(string $question, ?string $moduleContext, array $history = []): ?ToolPlan
    {
        if (! (bool) config('ai_assistant.tools.enabled', true)) {
            return null;
        }

        if (! (bool) config('ai_assistant.tools.fallback_planner_enabled', true)) {
            return null;
        }

        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return null;
        }

        // Follow-up pronouns / short refs → reuse last mentioned entity from history.
        if ($this->looksLikeFollowUp($q)) {
            $fromHistory = $this->planFromHistory($history, $moduleContext);
            if ($fromHistory !== null) {
                return $fromHistory;
            }
        }

        // Module-biased operational questions with no code extracted by heuristics.
        if ($this->looksOperational($q)) {
            $modulePlan = $this->planFromModuleContext($q, $moduleContext);
            if ($modulePlan !== null) {
                return $modulePlan;
            }

            if ($this->registry->has('search_workspace_entities')) {
                $query = $this->extractSearchNeedle($question, $history);
                if ($query !== null && mb_strlen($query) >= 2) {
                    return new ToolPlan(ToolPlan::MODE_TOOLS, [
                        new ToolCallRequest('search_workspace_entities', ['query' => $query]),
                    ]);
                }
            }
        }

        return null;
    }

    private function looksLikeFollowUp(string $q): bool
    {
        if (preg_match('/\b(that|this|it|those|them|same|previous|earlier|above)\b/u', $q) === 1) {
            return true;
        }

        // Short operational follow-ups: "status?", "and the status?", "open it"
        return mb_strlen($q) <= 48
            && preg_match('/\b(status|state|open|show|details|where|progress)\b/u', $q) === 1;
    }

    private function looksOperational(string $q): bool
    {
        return preg_match(
            '/\b(status|state|how many|count|find|look\s*up|lookup|show me|list|where is|check|pending|open tickets|awaiting)\b/u',
            $q,
        ) === 1;
    }

    /**
     * @param  list<ConversationTurn>  $history
     */
    private function planFromHistory(array $history, ?string $moduleContext): ?ToolPlan
    {
        $blob = '';
        foreach (array_reverse($history) as $turn) {
            $blob .= ' '.$turn->content;
        }

        if (preg_match('/\b(TKT-\d+)\b/i', $blob, $m) === 1 && $this->registry->has('get_ticket_by_number')) {
            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('get_ticket_by_number', [
                    'ticket_number' => strtoupper($m[1]),
                ]),
            ]);
        }

        if (preg_match('/\b([A-Z]{2,}-[A-Z0-9]{1,3}-\d{3,})\b/i', $blob, $m) === 1
            && $this->registry->has('get_eapproval_submission_by_document_no')) {
            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('get_eapproval_submission_by_document_no', [
                    'document_no' => strtoupper($m[1]),
                ]),
            ]);
        }

        if (preg_match('/\b([A-Z]{2,}(?:-[A-Z0-9]+){2,})\b/', $blob, $m) === 1
            && $this->registry->has('get_controlled_document_by_code')
            && ($moduleContext === 'document_register' || preg_match('/document|register|revision/i', $blob) === 1)) {
            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('get_controlled_document_by_code', [
                    'document_code' => strtoupper($m[1]),
                ]),
            ]);
        }

        if (preg_match('/\bsite(?:\s+code)?\s*[:=]?\s*([A-Z0-9][A-Z0-9\-_]{1,63})\b/i', $blob, $m) === 1
            && $this->registry->has('get_site_by_code')) {
            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('get_site_by_code', [
                    'site_code' => $m[1],
                ]),
            ]);
        }

        return null;
    }

    private function planFromModuleContext(string $q, ?string $moduleContext): ?ToolPlan
    {
        return match ($moduleContext) {
            'ticketing' => $this->registry->has('list_my_open_tickets')
                ? new ToolPlan(ToolPlan::MODE_TOOLS, [new ToolCallRequest('list_my_open_tickets')])
                : null,
            'e_approval' => $this->planEApprovalFallback($q),
            'sites' => null,
            'documents', 'document_register' => $this->registry->has('list_expiring_documents')
                && preg_match('/\b(expir|due soon)\b/u', $q) === 1
                ? new ToolPlan(ToolPlan::MODE_TOOLS, [new ToolCallRequest('list_expiring_documents', ['within_days' => 90])])
                : null,
            default => null,
        };
    }

    private function planEApprovalFallback(string $q): ?ToolPlan
    {
        if (preg_match('/\b(approv|awaiting me|to approve)\b/u', $q) === 1
            && $this->registry->has('list_my_pending_approvals')) {
            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('list_my_pending_approvals'),
            ]);
        }

        if ($this->registry->has('list_my_eapproval_submissions')) {
            $status = 'open';
            if (str_contains($q, 'approved')) {
                $status = 'approved';
            } elseif (str_contains($q, 'pending')) {
                $status = 'pending';
            } elseif (str_contains($q, 'returned')) {
                $status = 'returned';
            } elseif (str_contains($q, 'draft')) {
                $status = 'draft';
            }

            return new ToolPlan(ToolPlan::MODE_TOOLS, [
                new ToolCallRequest('list_my_eapproval_submissions', ['status' => $status]),
            ]);
        }

        return null;
    }

    /**
     * @param  list<ConversationTurn>  $history
     */
    private function extractSearchNeedle(string $question, array $history): ?string
    {
        if (preg_match('/\b(?:status|state)\s+of\s+(.+)$/iu', trim($question), $m) === 1) {
            $needle = trim($m[1], " \t\n\r\0\x0B\"'?.");

            return $needle !== '' ? mb_substr($needle, 0, 120) : null;
        }

        if (preg_match('/\b(?:search(?:\s+for)?|find(?:\s+the)?|look\s*up|lookup)\s+(.+)$/iu', trim($question), $m) === 1) {
            $needle = trim($m[1], " \t\n\r\0\x0B\"'?.");

            return $needle !== '' ? mb_substr($needle, 0, 120) : null;
        }

        // Follow-up with no explicit needle — use last user entity-like token from history.
        foreach (array_reverse($history) as $turn) {
            if ($turn->role !== 'user') {
                continue;
            }
            if (preg_match('/\b([A-Z]{2,}(?:-[A-Z0-9]+)+)\b/', $turn->content, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
