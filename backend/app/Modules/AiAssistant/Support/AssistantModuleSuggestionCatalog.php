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
                'How do I create an E-Approval request?',
                'Where do I track my E-Approval submission?',
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
            'sites' => [
                'How do I find a site by site code?',
                'What is linked to a site in TowerOS?',
                'How do I get started in TowerOS?',
            ],
            'project_one' => [
                'How do I find a rollout?',
                'How do gate approvals work?',
                'How do I get started in TowerOS?',
            ],
            'procurement_one' => [
                'How does the purchase order workflow work?',
                'How do I raise a ticket for a GRN mismatch?',
                'How do I track a delayed delivery on a PO?',
            ],
            'team_access' => [
                'How do I assign roles to a user?',
                'Why can’t a user see a module?',
                'Why can’t I see a page or module?',
            ],
            default => [
                'How do I get started in TowerOS?',
                'Why can’t I see a page or module?',
                'How do I create an E-Approval request?',
            ],
        };
    }
}
