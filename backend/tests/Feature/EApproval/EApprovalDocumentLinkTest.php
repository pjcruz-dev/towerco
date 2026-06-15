<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalDocumentLinkTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_submission_detail_includes_outgoing_and_incoming_document_links(): void
    {
        [$sourceId, $targetId] = $this->createTwoSubmissions();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$sourceId}/document-links", [
                'target_submission_id' => $targetId,
                'link_type' => 'related',
            ])
            ->assertCreated();

        $sourceDetail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$sourceId}")
            ->assertOk()
            ->json('data');

        $targetDetail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$targetId}")
            ->assertOk()
            ->json('data');

        $this->assertSame($targetId, $sourceDetail['document_links'][0]['submission_id']);
        $this->assertSame('outgoing', $sourceDetail['document_links'][0]['direction']);
        $this->assertSame($sourceId, $targetDetail['incoming_document_links'][0]['submission_id']);
        $this->assertSame('incoming', $targetDetail['incoming_document_links'][0]['direction']);
    }

    public function test_cannot_create_duplicate_document_link(): void
    {
        [$sourceId, $targetId] = $this->createTwoSubmissions();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$sourceId}/document-links", [
                'target_submission_id' => $targetId,
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$sourceId}/document-links", [
                'target_submission_id' => $targetId,
            ])
            ->assertStatus(422);
    }

    public function test_delete_document_link_updates_detail_payload(): void
    {
        [$sourceId, $targetId] = $this->createTwoSubmissions();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$sourceId}/document-links", [
                'target_submission_id' => $targetId,
            ])
            ->assertCreated()
            ->json('data');

        $linkId = (string) $create['link']['id'];

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson("/api/v1/e-approval/document-links/{$linkId}")
            ->assertOk()
            ->assertJsonPath('data.document_links', []);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$targetId}")
            ->assertOk()
            ->assertJsonPath('data.incoming_document_links', []);
    }

    public function test_submission_detail_includes_related_form_navigation(): void
    {
        $relatedFormId = $this->createPublishedForm('Liquidation');
        $formId = $this->createPublishedForm('Cash advance');

        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->findOrFail($formId);
        $form->related_form_ids = [$relatedFormId];
        $form->save();
        tenancy()->end();

        $submissionId = $this->createSubmission($formId, ['title' => 'Travel']);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}")
            ->assertOk()
            ->json('data');

        $this->assertSame($relatedFormId, $detail['related_form_navigation'][0]['form_id']);
        $this->assertSame('Liquidation', $detail['related_form_navigation'][0]['form_name']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createTwoSubmissions(): array
    {
        $formId = $this->createPublishedForm('Generic request');

        $sourceId = $this->createSubmission($formId, ['title' => 'Source document']);
        $targetId = $this->createSubmission($formId, ['title' => 'Target document']);

        return [$sourceId, $targetId];
    }

    private function createPublishedForm(string $name): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => $name,
                'description' => 'Document link test',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createSubmission(string $formId, array $values): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => $values,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.id');
    }
}
