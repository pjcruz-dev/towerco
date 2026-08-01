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
            'update document metadata',
            'suggest document metadata',
            'set document expiry',
            'rename document',
            'update document title',
        ]) || ($moduleContext === 'documents' && $this->matches($q, ['update metadata', 'set expiry', 'change title']))) {
            return ['action' => 'suggest_document_metadata', 'args' => []];
        }

        return null;
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
