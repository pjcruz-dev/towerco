<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Keyword intent detection for modular system-prompt assembly.
 * Detection lives in code — prompt module bodies are markdown/text only (never eval'd).
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

        if (preg_match('/\b(entity|database schema|tables?|fields?|relations?|backend|structures?|erp architecture|create entity|add field|new table|new entity|schema design|integrate|integration)\b/', $promptText)
            || preg_match('/\blink(?:ed)?\s+(?:entit|table|record|module|field|relation)/', $promptText)
        ) {
            $intents[] = 'system';
        }

        if (preg_match('/\b(frontend|ui|ux|theme|color|style|sidebar|menu|widgets?|charts?|layouts?|screens?|views?|design|html|css|styling|dark mode|light mode|navigation)\b/', $promptText)) {
            $intents[] = 'frontend';
        }

        if (preg_match('/\b(custom app|html app|html template|html page|custom tool|javascript app|js app|interactive app|kanban|portal|pos|point.?of.?sale)\b/', $promptText)) {
            $intents[] = 'app_dev';
            $intents[] = 'frontend';
        }

        if (preg_match('/\b(sample data|dummy data|populate|seed data|import data|export data|bulk import|insert records|seed|delete record|update record|records?)\b/', $promptText)) {
            $intents[] = 'data';
        }

        if (preg_match('/\b(report|summary|statement|dashboard|analytics|kpi|trial balance|balance sheet|income statement|cash flow|aging report|accounts receivable|accounts payable)\b/', $promptText)) {
            $intents[] = 'reporting';
        }

        if (preg_match('/\b(printable|pdf template|print invoice|pdf report|invoice template|journal voucher|payslip|official receipt|bir form)\b/', $promptText)) {
            $intents[] = 'printable';
        }

        if (preg_match('/\b(erp architecture|system design|module architecture|entity relationships|architecture plan)\b/', $promptText)) {
            $intents[] = 'architect';
        }

        if (preg_match('/\b(workflow|cron|schedule|automate|recurring|approval flow|notification rule|process button|status transition)\b/', $promptText)) {
            $intents[] = 'workflow';
        }

        if (preg_match('/\b(location|map|gps|coordinate|geofence)\b/', $promptText)) {
            $intents[] = 'location';
            $intents[] = 'frontend';
        }

        if (preg_match('/\b(audit|health check|system check|diagnostic|database optimization)\b/', $promptText)) {
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

        if (preg_match('/\b(dynamic entit|dyn record|html report|manage fields|field group)\b/', $promptText)) {
            $intents[] = 'system';
            $intents[] = 'data';
        }

        return array_values(array_unique($intents));
    }
}
