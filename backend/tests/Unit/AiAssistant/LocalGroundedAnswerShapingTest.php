<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Support\AssistantChunkRanker;
use App\Modules\AiAssistant\Support\LocalGroundedLlmProvider;
use Tests\TestCase;

final class LocalGroundedAnswerShapingTest extends TestCase
{
    public function test_submit_document_approval_prefers_submit_guide_over_approve(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Prerequisites

- You have `e_approval:submissions:create`.

## Steps

1. Open **E-Approval → New submission**.
2. Select the Document Control form.
3. Complete fields and submit.

## Expected result

A Document Approval submission is created.
MD,
            0.4,
        );

        $approve = $this->chunk(
            'e-approval-approve-request',
            'Approve an E-Approval request',
            <<<'MD'
# Approve an E-Approval request

This guide is for approvers only.

## Steps

1. Open Approvals.
2. Approve or return.
MD,
            0.55,
        );

        $ranked = (new AssistantChunkRanker)->rank([$approve, $submit], 'How to submit a request using Document Approval?');
        $this->assertSame('document-approval-submit-request', $ranked[0]->slug);

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nHow to submit a request using Document Approval?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$approve, $submit],
        ));

        $this->assertStringContainsString('Submit a Document Approval request', $result->answer);
        $this->assertStringContainsString('E-Approval → New submission', $result->answer);
        $this->assertStringContainsString('Document Control', $result->answer);
        $this->assertStringNotContainsString('### 1.', $result->answer);
        $this->assertStringNotContainsString('Sources:', $result->answer);
        $this->assertStringNotContainsStringIgnoringCase('Open Approvals', $result->answer);
    }

    public function test_track_document_approval_returns_tracking_steps_not_submit_flow(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Steps

1. Open **E-Approval → New submission**.
2. Select the Document Control form.
3. Submit when ready.

## Track your submission

1. Open **E-Approval → Submissions**.
2. Find your request by document number or status.
3. Open the submission to review progress.

## Expected result

Workflow progress is visible on the submission detail page.
MD,
            0.45,
        );

        $create = $this->chunk(
            'e-approval-create-request',
            'Create an E-Approval request',
            <<<'MD'
# Create an E-Approval request

General E-Approval create flow.

## Steps

1. Open E-Approval.
2. New submission.
MD,
            0.5,
        );

        $ranked = (new AssistantChunkRanker)->rank(
            [$create, $submit],
            'Where do I track my Document Approval submission?',
        );
        $this->assertSame('document-approval-submit-request', $ranked[0]->slug);

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nWhere do I track my Document Approval submission?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$create, $submit],
        ));

        $this->assertStringContainsString('Track your Document Approval submission', $result->answer);
        $this->assertStringContainsString('E-Approval → Submissions', $result->answer);
        $this->assertStringNotContainsString('New submission', $result->answer);
        $this->assertStringNotContainsString('Also useful:', $result->answer);
        $this->assertStringNotContainsString('Prerequisites', $result->answer);
    }

    public function test_generic_create_eapproval_prefers_general_guide_not_document_approval(): void
    {
        $submitDoc = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this for Document Approval / ISO Document Control submissions.

## Steps

1. Open **E-Approval → New submission**.
2. Select the Document Control form.
MD,
            0.5,
        );

        $create = $this->chunk(
            'e-approval-create-request',
            'Create an E-Approval request',
            <<<'MD'
# Create an E-Approval request

Use E-Approval to submit forms for review (cash advances, procurement-related forms, and other tenant workflows).

## Steps

1. Open **E-Approval**.
2. Choose **New submission**.
3. Select the published form you need.
MD,
            0.45,
        );

        $question = 'How do I create an E-Approval request?';
        $ranked = (new AssistantChunkRanker)->rank([$submitDoc, $create], $question);
        $this->assertSame('e-approval-create-request', $ranked[0]->slug);

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\n{$question}\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$submitDoc, $create],
        ));

        // Primary answer is the general create guide, cleanly shaped (no fragment/heading noise).
        $this->assertStringContainsString('Create an E-Approval request', $result->answer);
        $this->assertStringContainsString('published form', $result->answer);
        $this->assertStringNotContainsString('# Create an E-Approval request', $result->answer);
        $this->assertStringNotContainsString('Related workflows', $result->answer);
        // The title must not be duplicated back-to-back in the body.
        $this->assertSame(
            1,
            substr_count($result->answer, 'Create an E-Approval request'),
        );
    }

    public function test_multiple_chunks_from_same_source_are_collapsed(): void
    {
        $chunkA = $this->chunk(
            'getting-started',
            'Getting started with TowerOS',
            "# Getting started with TowerOS\n\nWelcome.\n\n## Steps\n\n1. Open the dashboard.",
            0.6,
        );
        $chunkB = new RetrievedKnowledgeChunk(
            chunkId: 'getting-started-2',
            sourceId: 'getting-started',
            vectorId: 'getting-started-v2',
            content: 'More getting started content in a later chunk.',
            score: 0.55,
            scope: 'global',
            moduleKey: null,
            title: 'Getting started with TowerOS',
            slug: 'getting-started',
            version: 1,
            permissions: [],
            relatedRoutes: [],
        );

        $ranked = (new AssistantChunkRanker)->rank([$chunkA, $chunkB], 'How do I get started?');

        $this->assertCount(1, $ranked);
        $this->assertSame('getting-started', $ranked[0]->slug);
    }

    public function test_tool_failure_returns_explicit_permission_message_not_generic_docs(): void
    {
        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nHow many pending requests do I have?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            toolResults: [
                new ToolResult(
                    tool: 'list_my_eapproval_submissions',
                    ok: false,
                    error: 'Missing permission: e_approval:submissions:view',
                    moduleKey: 'e_approval',
                ),
            ],
        ));

        $this->assertStringContainsString('I could not check the live system data', $result->answer);
        $this->assertStringContainsString('Missing permission: e_approval:submissions:view', $result->answer);
        $this->assertStringNotContainsString('Create an E-Approval request', $result->answer);
    }

    public function test_returned_for_revision_answers_resubmit_flow_not_full_submit_guide(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Steps

1. Open **E-Approval → New submission**.
2. Submit when ready.

## Common errors

- **Returned for revision** — open the submission, update answers/files, and resubmit.
MD,
            0.5,
        );

        $question = 'What if my submission was returned for revision?';
        $this->assertSame('returned', (new AssistantChunkRanker)->detectIntent($question));

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\n{$question}\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$submit],
        ));

        $this->assertStringContainsString('returned for revision', mb_strtolower($result->answer));
        $this->assertStringContainsString('Resubmit', $result->answer);
        $this->assertStringNotContainsString('New submission', $result->answer);
        $this->assertStringNotContainsString('Also useful:', $result->answer);
        $this->assertSame(
            1,
            substr_count($result->answer, 'If your submission was returned for revision'),
        );
    }

    public function test_form_not_listed_answers_troubleshooting_not_full_submit_guide(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Steps

1. Open **E-Approval → New submission**.
2. Select the Document Control form.
3. Submit when ready.

## Common errors

- **Document Control form not listed** — form not published, wrong form family, or missing access.
- **Cannot submit** — required fields missing or validation failed; fix highlighted fields.
MD,
            0.5,
        );

        $create = $this->chunk(
            'e-approval-create-request',
            'Create an E-Approval request',
            <<<'MD'
# Create an E-Approval request

General E-Approval create flow.

## Common errors

- **Form not listed** — form not published, or you lack access.
MD,
            0.4,
        );

        $question = 'What if my Document Control form is not listed?';
        $this->assertSame('form_missing', (new AssistantChunkRanker)->detectIntent($question));

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\n{$question}\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$submit, $create],
        ));

        $this->assertStringContainsString('Document Control form is not listed', $result->answer);
        $this->assertStringContainsString('not published', mb_strtolower($result->answer));
        $this->assertStringContainsString('e_approval:submissions:create', $result->answer);
        $this->assertStringNotContainsString('Complete required fields', $result->answer);
        $this->assertStringNotContainsString('Also useful:', $result->answer);
        $this->assertStringNotContainsString('Expected result', $result->answer);
    }

    public function test_crlf_prompt_still_extracts_create_question_not_returned_flow(): void
    {
        $createBody = <<<'MD'
# Create an E-Approval request

Use E-Approval to submit forms for review.

## Prerequisites

- E-Approval module is enabled.

## Steps

1. Open **E-Approval**.
2. Choose **New submission** / **Submissions → New**.
3. Select the published form you need.
4. Complete required fields.
5. Submit when ready.

## Common errors

- **Returned for revision** — open the submission, update answers, and resubmit.
MD;

        $create = $this->chunk(
            'e-approval-create-request',
            'Create an E-Approval request',
            $createBody,
            0.5,
        );

        // Windows/CRLF heredoc style — previously caused extractQuestion to swallow
        // CONTEXT and mis-detect intent as "returned".
        $user = "USER_QUESTION:\r\nHow do I create an E-Approval request?\r\n\r\nBEGIN_LIVE_SYSTEM_DATA\r\n(no live)\r\nEND_LIVE_SYSTEM_DATA\r\n\r\nBEGIN_UNTRUSTED_CONTEXT\r\n{$createBody}\r\nEND_UNTRUSTED_CONTEXT";

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: $user,
            chunks: [$create],
        ));

        $this->assertStringContainsString('Create an E-Approval request', $result->answer);
        $this->assertStringContainsString('New submission', $result->answer);
        $this->assertStringNotContainsString('If your submission was returned for revision', $result->answer);
        $this->assertSame('submit', (new AssistantChunkRanker)->detectIntent('How do I create an E-Approval request?'));
    }

    public function test_off_topic_question_returns_out_of_scope_not_nearest_doc(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Steps

1. Open **E-Approval → New submission**.
MD,
            0.4,
        );

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nWrite me a poem about cats and the weather today\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$submit],
        ));

        $this->assertTrue($result->insufficientContext);
        $this->assertStringContainsString('TowerOS workspace assistant', $result->answer);
        $this->assertStringNotContainsString('Submit a Document Approval request', $result->answer);
    }

    public function test_relevant_question_still_answered_after_out_of_scope_guard(): void
    {
        $submit = $this->chunk(
            'document-approval-submit-request',
            'Submit a Document Approval request',
            <<<'MD'
# Submit a Document Approval request

Use this when you need to submit a Document Approval request.

## Steps

1. Open **E-Approval → New submission**.
2. Select the Document Control form.
MD,
            0.4,
        );

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nHow do I submit a Document Approval request?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$submit],
        ));

        $this->assertFalse($result->insufficientContext);
        $this->assertStringContainsString('Submit a Document Approval request', $result->answer);
    }

    public function test_document_register_steps_are_complete_not_truncated_mid_sentence(): void
    {
        $register = $this->chunk(
            'document-register',
            'Document register (controlled documents)',
            <<<'MD'
# Document register (controlled documents)

The document register is the ISO-style master list of controlled documents. Use it to find the approved revision. To **submit** a new controlled document or revision for approval, use **Submit a Document Approval request** (E-Approval Document Control form).

## Prerequisites

- Document register module is enabled.
- You have `documents:controlled:view`.
- Creating or managing controlled documents may require additional controlled-document permissions.

## Steps

1. Open **Document register**.
2. Search by document code or title.
3. Open a controlled document to see status, current revision, and department.
4. If you need a new document or revision, start **E-Approval → New submission** and choose the Document Control / ISO form.
5. Download or stream the published revision only when your role allows it.

## Expected result

You locate the correct controlled document and know which revision is current and whether it is published or obsolete.
MD,
            0.5,
            'document_register',
        );

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nHow do I find a controlled document in the register?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$register],
        ));

        $this->assertStringContainsString('E-Approval → New submission', $result->answer);
        $this->assertStringContainsString('Document Control / ISO form', $result->answer);
        $this->assertStringContainsString('Download or stream the published revision', $result->answer);
        $this->assertStringNotContainsString('New sub…', $result->answer);
    }

    public function test_document_status_question_uses_live_tool_not_register_how_to(): void
    {
        $register = $this->chunk(
            'document-register',
            'Document register (controlled documents)',
            <<<'MD'
# Document register (controlled documents)

The document register is the ISO-style master list of controlled documents.

## Steps

1. Open **Document register**.
2. Search by document code or title.
MD,
            0.5,
            'document_register',
        );

        $provider = new LocalGroundedLlmProvider;
        $result = $provider->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nWhat is the status of ATC-F-HR-003-R001?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$register],
            toolResults: [
                new ToolResult(
                    tool: 'get_controlled_document_by_code',
                    ok: true,
                    data: [
                        'document' => [
                            'document_code' => 'ATC-F-HR-003-R001',
                            'title' => 'HR Policy',
                            'status' => 'published',
                            'current_revision' => 1,
                            'href' => '/documents/controlled?document=abc',
                        ],
                    ],
                    summary: 'Document ATC-F-HR-003-R001 — HR Policy. Status: published. Current revision: 1.',
                    moduleKey: 'document_register',
                    relatedRoutes: ['/documents/controlled?document=abc'],
                    rowCount: 1,
                ),
            ],
        ));

        $this->assertStringContainsString('From live system data', $result->answer);
        $this->assertStringContainsString('ATC-F-HR-003-R001', $result->answer);
        $this->assertStringContainsString('Status: published', $result->answer);
        $this->assertStringNotContainsString('Open **Document register**', $result->answer);
        $this->assertStringNotContainsString('Also useful:', $result->answer);
    }

    private function chunk(
        string $slug,
        string $title,
        string $content,
        float $score,
        string $moduleKey = 'e_approval',
    ): RetrievedKnowledgeChunk
    {
        return new RetrievedKnowledgeChunk(
            chunkId: $slug.'-1',
            sourceId: $slug,
            vectorId: $slug.'-v',
            content: $content,
            score: $score,
            scope: 'global',
            moduleKey: $moduleKey,
            title: $title,
            slug: $slug,
            version: 1,
            permissions: [],
            relatedRoutes: ['/e-approval/submissions/new'],
        );
    }
}
