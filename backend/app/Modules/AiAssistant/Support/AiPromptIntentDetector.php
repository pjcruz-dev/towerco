<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Keyword intent detection for modular system-prompt assembly.
 * Detection lives in code — prompt module bodies are markdown/text only (never eval'd).
 *
 * Co-load bundles mirror MetaCore Soft router behavior (accounting fan-in, printable rescue, etc.).
 */
final class AiPromptIntentDetector
{
    /**
     * @param  list<string>  $historyUserTexts  Prior user turns (sanitized), newest last
     * @return list<string> Unique intent keys
     */
    public function detect(string $prompt, array $historyUserTexts = [], bool $hasFiles = false): array
    {
        $promptText = strtolower($prompt);
        foreach ($historyUserTexts as $turn) {
            $txt = strtolower(trim($turn));
            if ($txt === '') {
                continue;
            }
            if (
                str_contains($txt, 'system auto-followup')
                || str_contains($txt, 'your previous response was')
                || str_contains($txt, 'you were provided with tool results')
            ) {
                continue;
            }
            $promptText .= ' '.$txt;
        }

        $intents = [];

        if ($hasFiles) {
            $intents[] = 'system';
            $intents[] = 'architect';
        }

        if (preg_match('/\b(entity|database schema|tables?|fields?|relations?|backend|structures?|erp architecture|create entity|add field|new table|new entity|schema design|integrate|integration|build (?:a |an )?(?:new )?system|rebuild)\b/', $promptText)
            || preg_match('/\blink(?:ed)?\s+(?:entit|table|record|module|field|relation)/', $promptText)
        ) {
            $intents[] = 'system';
        }

        if (preg_match('/\b(frontend|ui|ux|theme|color|style|sidebar|menu|widgets?|charts?|layouts?|screens?|views?|design|html|css|styling|dark mode|light mode|navigation)\b/', $promptText)) {
            $intents[] = 'frontend';
        }

        if (preg_match('/\b(custom app|html app|html template|html page|custom tool|javascript app|js app|interactive app|kanban|portal|pos|point.?of.?sale|calculator)\b/', $promptText)) {
            $intents[] = 'app_dev';
            $intents[] = 'frontend';
        }

        if (preg_match('/\b(sample data|dummy data|populate|seed data|import data|export data|bulk import|insert records|seed|delete record|update record|records?)\b/', $promptText)) {
            $intents[] = 'data';
        }

        $matchedReporting = preg_match(
            '/\b(report|summary|statement|dashboard|analytics|kpi|trial balance|balance sheet|income statement|cash flow|aging report|accounts receivable|accounts payable|full\s+details?|details?\s+of|list\s+of|all\s+columns|not\s+only|every\s+record)\b/',
            $promptText,
        ) === 1;
        if ($matchedReporting) {
            $intents[] = 'reporting';
        }

        $matchedPrintable = preg_match(
            '/\b(printable|pdf template|print invoice|pdf report|invoice template|journal voucher|payslip|official receipt|bir form|letterhead|control\s+(?:no\.?|number)|signature\s+block|bond\s+paper|a4|waybill|gate\s+pass)\b/',
            $promptText,
        ) === 1;
        if ($matchedPrintable) {
            $intents[] = 'printable';
        } elseif ($matchedReporting && preg_match('/\b(control\s+(?:no\.?|number)|letterhead|signature\s+block|second\s+page|2nd\s+page|page\s+break|printout|margins?)\b/', $promptText) === 1) {
            // MetaCore "printable rescue": paper symptoms on a "report" ask.
            $intents[] = 'printable';
        }

        if (preg_match('/\b(erp architecture|system design|module architecture|entity relationships|architecture plan|blueprint|requirements?\s+(?:doc|document|spec))\b/', $promptText)) {
            $intents[] = 'architect';
        }

        if (preg_match('/\b(workflow|cron|schedule|automate|recurring|approval flow|notification rule|process button|status (?:button|transition)|entity\s+hooks?|fan-?out|before_update|after_create)\b/', $promptText)) {
            $intents[] = 'workflow';
        }

        // Fan-out / one-document-per-group → workflow + system (schema prerequisites).
        if (preg_match('/\b(one|separate|individual)\b.{0,40}\b(per|for each|by)\b/i', $promptText) === 1
            || preg_match('/\b(group(?:ed)?\s+by|split|fan-?out)\b.{0,40}\b(supplier|vendor|warehouse|site|customer)\b/i', $promptText) === 1
        ) {
            $intents[] = 'workflow';
            $intents[] = 'system';
        }

        if (preg_match('/\b(location|map|gps|coordinate|geofence|tower\s+sites?|site\s+code|sites?\s+inventory)\b/', $promptText)) {
            $intents[] = 'location';
            $intents[] = 'frontend';
            if ($matchedReporting || preg_match('/\b(details?|list|report|dashboard|not\s+only)\b/', $promptText) === 1) {
                $intents[] = 'reporting';
            }
        }

        if (preg_match('/\b(audit|health check|system check|diagnostic|database optimization|search\s+index)\b/', $promptText)) {
            $intents[] = 'audit';
            $intents[] = 'system';
        }

        if (preg_match('/\b(accounting|finance|general ledger|chart of accounts|coa|journal entry|double.?entry|debit|credit|bank reconciliation|withholding tax|vat)\b/', $promptText)) {
            $intents[] = 'accounting';
            $intents[] = 'system';
            $intents[] = 'reporting';
            $intents[] = 'workflow';
            $intents[] = 'printable';
        }

        if (preg_match('/\b(payroll|payslip|salary|sss|philhealth|pagibig|hdmf|thirteenth.?month|overtime|timesheet)\b/', $promptText)) {
            $intents[] = 'payroll';
            $intents[] = 'accounting';
            $intents[] = 'workflow';
            $intents[] = 'printable';
        }

        if (preg_match('/\b(ticket|ticketing|sla|incident|service request)\b/', $promptText)) {
            $intents[] = 'ticketing';
        }

        if (preg_match('/\b(e-?approval|approval request|approver|gate approval)\b/', $promptText)) {
            $intents[] = 'e_approval';
        }

        if (preg_match('/\b(dynamic entit|dyn record|html report|manage fields|field group|report builder)\b/', $promptText)) {
            $intents[] = 'system';
            $intents[] = 'data';
            $intents[] = 'reporting';
        }

        return array_values(array_unique($intents));
    }
}
