<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Module-aware suggested follow-ups aligned with the frontend route-context catalog.
 */
final class AssistantModuleSuggestionCatalog
{
    /**
     * @return list<string>
     */
    public function forModule(?string $moduleKey): array
    {
        return match ($moduleKey) {
            'e_approval' => [
                'How do I create an E-Forms request?',
                'Where do I track my E-Forms submission?',
                'What if my form is not listed?',
            ],
            'ticketing' => [
                'How do I create a ticket?',
                'What is the status of TKT-00001?',
                'Who can assign tickets?',
            ],
            'document_register' => [
                'How do I use the document register?',
                'How do I submit a Document Approval request?',
                'How do I find the current controlled revision?',
            ],
            'documents' => [
                'How do I upload a document to a site binder?',
                'Where do I track expiring documents?',
                'How do I find a controlled document?',
            ],
            'team_access' => [
                'How do I assign roles to a user?',
                'Why can’t a user see a module?',
                'Why can’t I see a page or module?',
            ],
            default => [
                'How do I get started in INFRA SUITE?',
                'Why can’t I see a page or module?',
                'How do I create an E-Forms request?',
            ],
        };
    }
}
