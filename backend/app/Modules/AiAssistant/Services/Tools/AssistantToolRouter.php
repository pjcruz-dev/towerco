<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\DTOs\ToolPlan;

/**
 * Deterministic heuristic router: docs vs tools vs both.
 * Never lets the LLM invent tool names or SQL — only allowlisted tools from patterns.
 */
final class AssistantToolRouter
{
    public function plan(string $question, ?string $moduleContext = null): ToolPlan
    {
        if (! (bool) config('ai_assistant.tools.enabled', true)) {
            return new ToolPlan(ToolPlan::MODE_DOCS);
        }

        $q = mb_strtolower(trim($question));
        $calls = [];
        $mentionsEApprovalRequest = $this->matches($q, [
            'pending request',
            'pending requests',
            'pending submission',
            'pending submissions',
            'my request',
            'my requests',
            'my submission',
            'my submissions',
            'e-approval request',
            'e-approval submission',
            'approval request',
            'approval submission',
            'approved request',
            'approved requests',
            'rejected request',
            'rejected requests',
            'returned request',
            'returned requests',
            'draft request',
            'draft requests',
            'request i have',
            'requests i have',
            'submission i have',
            'submissions i have',
        ]);
        $asksForCountOrStatus = $this->matches($q, [
            'how many',
            'count',
            'total',
            'status',
            'do i have',
            'i have',
            'show me',
            'list',
            'pending',
            'open',
        ]);

        if ($this->matches($q, [
            'pending approval',
            'pending approvals',
            'awaiting my approval',
            'approvals waiting',
            'what do i need to approve',
            'my pending approvals',
            'approvals for me',
            'waiting for my approval',
            'requests waiting for me',
            'requests to approve',
            'request waiting for my approval',
        ]) || ($moduleContext === 'e_approval' && $this->matches($q, ['awaiting me', 'to approve']))) {
            $calls[] = new ToolCallRequest('list_my_pending_approvals');
        }

        if (($mentionsEApprovalRequest && $asksForCountOrStatus)
            || ($moduleContext === 'e_approval' && $this->matches($q, ['my pending request', 'my open request', 'my submissions']))
        ) {
            $status = 'open';
            if ($this->matches($q, ['draft'])) {
                $status = 'draft';
            } elseif ($this->matches($q, ['approved'])) {
                $status = 'approved';
            } elseif ($this->matches($q, ['rejected'])) {
                $status = 'rejected';
            } elseif ($this->matches($q, ['cancelled', 'canceled'])) {
                $status = 'cancelled';
            } elseif ($this->matches($q, ['returned'])) {
                $status = 'returned';
            } elseif ($this->matches($q, ['all'])) {
                $status = 'all';
            } elseif ($this->matches($q, ['pending'])) {
                $status = 'pending';
            }

            $calls[] = new ToolCallRequest('list_my_eapproval_submissions', [
                'status' => $status,
            ]);
        }

        if ($ticketNumber = $this->extractTicketNumber($question)) {
            $calls[] = new ToolCallRequest('get_ticket_by_number', [
                'ticket_number' => $ticketNumber,
            ]);
        }

        if ($submissionNo = $this->extractSubmissionDocumentNo($question, $moduleContext)) {
            $calls[] = new ToolCallRequest('get_eapproval_submission_by_document_no', [
                'document_no' => $submissionNo,
            ]);
        }

        if ($documentCode = $this->extractControlledDocumentCode($question)) {
            $calls[] = new ToolCallRequest('get_controlled_document_by_code', [
                'document_code' => $documentCode,
            ]);
        }

        if ($this->matches($q, [
            'site code',
            'find site',
            'look up site',
            'lookup site',
            'get site',
            'which site',
        ]) || ($documentCode === null && $ticketNumber === null && $submissionNo === null
            && preg_match('/\bsite\s+[A-Za-z0-9\-_]{2,}\b/i', $question) === 1)) {
            $code = $this->extractSiteCode($question);
            $calls[] = new ToolCallRequest('get_site_by_code', array_filter([
                'site_code' => $code,
            ]));
        }

        if ($this->matches($q, [
            'my open tickets',
            'my tickets',
            'open tickets',
            'tickets assigned',
            'ticket status',
            'list my tickets',
        ]) || ($moduleContext === 'ticketing' && $ticketNumber === null && $this->matches($q, ['open', 'my ticket', 'assigned to me']))) {
            $calls[] = new ToolCallRequest('list_my_open_tickets');
        }

        // Ambiguous "status/find of CODE" with no known prefix → workspace search across modules.
        if ($calls === [] && $this->looksLikeEntityStatusLookup($q) && ($entityQuery = $this->extractEntityQuery($question)) !== null) {
            $calls[] = new ToolCallRequest('search_workspace_entities', [
                'query' => $entityQuery,
            ]);
        }

        if ($this->matches($q, [
            'expiring document',
            'expiring lease',
            'documents expiring',
            'leases expiring',
            'about to expire',
            'expiry',
            'expire soon',
        ]) || ($moduleContext === 'documents' && $this->matches($q, ['expir', 'due soon']))) {
            $days = $this->extractDays($q) ?? 90;
            $calls[] = new ToolCallRequest('list_expiring_documents', ['within_days' => $days]);
        }

        if ($this->matches($q, [
            'search for',
            'find the',
            'look up',
            'lookup',
            'where is',
            'search workspace',
        ]) && ! $this->matches($q, ['how do i', 'how to', 'what is the process', 'permissions'])) {
            // Prefer specific tools over generic workspace search when already matched.
            $alreadySpecific = false;
            foreach ($calls as $existing) {
                if (in_array($existing->tool, [
                    'get_ticket_by_number',
                    'get_eapproval_submission_by_document_no',
                    'get_controlled_document_by_code',
                    'get_site_by_code',
                    'list_my_pending_approvals',
                    'list_my_open_tickets',
                    'list_expiring_documents',
                    'search_workspace_entities',
                ], true)) {
                    $alreadySpecific = true;
                    break;
                }
            }

            if (! $alreadySpecific) {
                $searchQuery = $this->extractSearchQuery($question);
                if ($searchQuery !== null && mb_strlen($searchQuery) >= 2) {
                    $calls[] = new ToolCallRequest('search_workspace_entities', [
                        'query' => $searchQuery,
                    ]);
                }
            }
        }

        // Deduplicate by tool name (first wins).
        $unique = [];
        foreach ($calls as $call) {
            if (! isset($unique[$call->tool])) {
                $unique[$call->tool] = $call;
            }
        }
        $calls = array_values($unique);

        if ($calls === []) {
            return new ToolPlan(ToolPlan::MODE_DOCS);
        }

        // How-to / process questions still benefit from docs alongside live data.
        $wantsHowTo = $this->matches($q, [
            'how do i',
            'how to',
            'what is the process',
            'steps to',
            'guide',
            'explain',
        ]);

        return new ToolPlan(
            mode: $wantsHowTo ? ToolPlan::MODE_BOTH : ToolPlan::MODE_TOOLS,
            calls: $calls,
        );
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

    private function extractSiteCode(string $question): ?string
    {
        $q = mb_strtolower($question);
        $explicitSite = $this->matches($q, ['site code', 'find site', 'look up site', 'lookup site', 'get site', 'which site'])
            || preg_match('/\bsite\s+[A-Za-z0-9\-_]{2,}\b/i', $question) === 1;

        if (! $explicitSite
            && ($this->extractTicketNumber($question) !== null
                || $this->extractSubmissionDocumentNo($question, null) !== null
                || $this->extractControlledDocumentCode($question) !== null)) {
            return null;
        }

        if (preg_match('/\bsite(?:\s+code)?\s*[:=]?\s*([A-Za-z0-9][A-Za-z0-9\-_]{1,63})\b/i', $question, $m) === 1) {
            $candidate = $m[1];
            if (! in_array(mb_strtolower($candidate), ['code', 'named', 'called', 'for', 'the'], true)) {
                return $candidate;
            }
        }

        if (preg_match('/\b([A-Z]{2,}[\-_]?[0-9]{2,}[A-Za-z0-9\-_]*)\b/', $question, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractTicketNumber(string $question): ?string
    {
        if (preg_match('/\bTKT-0*([0-9]{1,10})\b/i', $question, $m) === 1) {
            return 'TKT-'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }

        if (preg_match('/\bticket(?:\s+(?:number|no\.?|#))?\s*[:=]?\s*(?:TKT-)?0*([0-9]{1,10})\b/i', $question, $m) === 1) {
            return 'TKT-'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function extractSubmissionDocumentNo(string $question, ?string $moduleContext): ?string
    {
        if ($this->extractTicketNumber($question) !== null) {
            return null;
        }

        $q = mb_strtolower($question);
        if ($this->matches($q, ['site code', 'site ', ' look up site', 'lookup site', 'find site'])) {
            return null;
        }

        // Explicit submission / request language.
        if (preg_match('/\b(?:submission|request|document\s*no\.?|doc\s*no\.?)\s+[:=]?\s*([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+)\b/i', $question, $m) === 1) {
            return strtoupper($m[1]);
        }

        // Typical E-Approval sequence numbers: OWNER-TYPE-00042 where TYPE is short (F, P, CA…).
        // Avoid site-style codes like PH-CEB-042 (longer region segment).
        if (preg_match('/\b([A-Z]{2,}-[A-Z0-9]{1,3}-\d{3,})\b/i', $question, $m) === 1) {
            $code = strtoupper($m[1]);
            if ($moduleContext === 'e_approval'
                || $this->matches($q, ['submission', 'e-approval', 'eapproval', 'request', 'status', 'state', 'where is', 'check'])) {
                return $code;
            }
        }

        // Revision submission numbers: ATC-P-SCM-001-R001 (E-Approval revision requests).
        if (preg_match('/\b([A-Z]{2,}(?:-[A-Z0-9]+)+-R\d{2,})\b/i', $question, $m) === 1
            && ($moduleContext === 'e_approval'
                || $this->matches($q, ['submission', 'request', 'approval', 'status', 'state', 'returned', 'where is', 'look up', 'lookup']))) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function extractControlledDocumentCode(string $question): ?string
    {
        // Ticket / submission codes must never be treated as register document codes.
        if ($this->extractTicketNumber($question) !== null) {
            return null;
        }

        $candidate = null;

        if (preg_match('/[`"\']([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+)[`"\']/i', $question, $m) === 1) {
            $candidate = $m[1];
        } elseif (preg_match('/\b(?:controlled document|doc(?:ument)? code|register)\s+([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+)\b/i', $question, $m) === 1) {
            $candidate = $m[1];
        } elseif (preg_match('/\b(?:status|revision|state)\s+of\s+([A-Z][A-Z0-9]*(?:-[A-Z0-9]+){2,})\b/i', $question, $m) === 1) {
            $candidate = $m[1];
            // OWNER-TYPE-##### and …-R001 are E-Approval document numbers, not register codes.
            if (preg_match('/^[A-Z]{2,}-[A-Z0-9]+-\d{3,}$/i', $candidate) === 1
                || preg_match('/-R\d{2,}$/i', $candidate) === 1) {
                return null;
            }
        } elseif (preg_match('/\b([A-Z]{2,}(?:-[A-Z0-9]+){3,})\b/', $question, $m) === 1) {
            $candidate = $m[1];
            if (preg_match('/-R\d{2,}$/i', $candidate) === 1) {
                return null;
            }
        }

        if ($candidate === null) {
            return null;
        }

        $upper = strtoupper($candidate);
        if (preg_match('/^(TKT|SITE|SUB|PO|PR|RFQ|GRN)-/i', $upper) === 1) {
            return null;
        }

        return $candidate;
    }

    private function looksLikeEntityStatusLookup(string $q): bool
    {
        return preg_match('/\b(status|state|where is|look\s*up|lookup|find|show|check)\b/u', $q) === 1
            && preg_match('/\b[a-z0-9]+(?:-[a-z0-9]+)+\b/u', $q) === 1;
    }

    private function extractEntityQuery(string $question): ?string
    {
        if (preg_match('/\b(?:status|state)\s+of\s+([A-Za-z0-9][A-Za-z0-9\-_]{1,63})\b/i', $question, $m) === 1) {
            return $m[1];
        }

        if (preg_match('/\b([A-Z]{2,}(?:-[A-Z0-9]+)+)\b/', $question, $m) === 1) {
            return $m[1];
        }

        return $this->extractSearchQuery($question);
    }

    private function extractDays(string $q): ?int
    {
        if (preg_match('/\bwithin\s+(\d{1,3})\s*days?\b/', $q, $m) === 1) {
            return max(1, min(365, (int) $m[1]));
        }
        if (preg_match('/\b(\d{1,3})\s*days?\b/', $q, $m) === 1) {
            return max(1, min(365, (int) $m[1]));
        }

        return null;
    }

    private function extractSearchQuery(string $question): ?string
    {
        if (preg_match('/\b(?:search(?:\s+for)?|find(?:\s+the)?|look\s*up|lookup)\s+(.+)$/iu', trim($question), $m) === 1) {
            $q = trim($m[1], " \t\n\r\0\x0B\"'");

            return $q !== '' ? mb_substr($q, 0, 120) : null;
        }

        return null;
    }
}
