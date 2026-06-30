<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Notifications\ProcurementRfqVendorMailNotification;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Services\ProcurementRfqVendorInvitationService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementRfqVendorPortalTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
                'procurement_one',
            ],
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        app(ProcurementOneSettingsService::class)->setJson(ProcurementOneSettingsService::RFQ_SCORING_POLICY, [
            'weight_price' => 50,
            'weight_lead_time' => 25,
            'weight_accreditation' => 15,
            'weight_line_coverage' => 10,
            'vendor_portal_enabled' => true,
        ]);
        tenancy()->end();
    }

    public function test_draft_rfq_quote_link_shows_read_only_portal_before_publish(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/vendors", [
                'vendor_ids' => [$vendorId],
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $invitation = ProcurementRfqVendor::query()->where('rfq_id', $rfqId)->firstOrFail();
        $encoded = app(ProcurementRfqVendorInvitationService::class)->encodeAccessToken(
            $this->issuePlainToken($invitation),
        );
        tenancy()->end();

        $show = $this->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/public/procurement/rfq-quotes/{$encoded}");
        $show->assertOk()
            ->assertJsonPath('data.rfq.id', $rfqId)
            ->assertJsonPath('data.rfq.status', 'draft')
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.submission_blocked_reason', fn ($value) => is_string($value) && $value !== '');

        $this->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/public/procurement/rfq-quotes/{$encoded}/bids", [
                'contact_name' => 'Vendor Contact',
                'lines' => [
                    ['rfq_line_id' => (string) $create->json('data.rfq.lines.0.id'), 'quantity' => 2, 'unit_price' => 900],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bid']);
    }

    public function test_invite_sends_vendor_email_and_public_portal_accepts_bid(): void
    {
        Notification::fake();

        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();
        $this->createPurchaseRequisitionForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');
        $lineId = (string) $create->json('data.rfq.lines.0.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/vendors", [
                'vendor_ids' => [$vendorId],
            ])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk()
            ->assertJsonPath('data.rfq.status', 'open');

        Notification::assertSentOnDemand(ProcurementRfqVendorMailNotification::class);

        tenancy()->initialize($this->testTenant);
        $invitation = ProcurementRfqVendor::query()->where('rfq_id', $rfqId)->firstOrFail();
        $plainToken = $this->issuePlainToken($invitation);
        tenancy()->end();

        $encoded = app(ProcurementRfqVendorInvitationService::class)->encodeAccessToken($plainToken);

        $show = $this->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/public/procurement/rfq-quotes/{$encoded}");
        $show->assertOk()
            ->assertJsonPath('data.rfq.id', $rfqId)
            ->assertJsonPath('data.can_submit', true);

        $submit = $this->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/public/procurement/rfq-quotes/{$encoded}/bids", [
                'contact_name' => 'Vendor Contact',
                'lines' => [
                    ['rfq_line_id' => $lineId, 'quantity' => 2, 'unit_price' => 900, 'lead_time_days' => 10],
                ],
            ]);
        $submit->assertCreated()
            ->assertJsonPath('data.bid.total_amount', 1800);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}");
        $detail->assertOk()
            ->assertJsonPath('data.rfq.bid_count', 1)
            ->assertJsonPath('data.rfq.invited_vendors.0.submitted_via', 'portal');
    }

    public function test_rfq_lines_sync_from_pr_when_pr_changes(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');

        tenancy()->initialize($this->testTenant);
        $prLine = \App\Modules\ProcurementOne\Models\ProcurementPrLine::query()
            ->where('pr_id', $prId)
            ->firstOrFail();
        $prLine->description = 'Updated PR line description';
        $prLine->quantity = 5;
        $prLine->unit_price = 1200;
        $prLine->save();
        tenancy()->end();

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}");
        $detail->assertOk()
            ->assertJsonPath('data.rfq.lines.0.description', 'Updated PR line description')
            ->assertJsonPath('data.rfq.lines.0.quantity', 5)
            ->assertJsonPath('data.rfq.lines_source', 'purchase_requisition');
    }

    public function test_vendor_can_revise_quotation_while_rfq_is_open(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');
        $lineId = (string) $create->json('data.rfq.lines.0.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $invitation = ProcurementRfqVendor::query()->where('rfq_id', $rfqId)->firstOrFail();
        $encoded = app(ProcurementRfqVendorInvitationService::class)->encodeAccessToken(
            $this->issuePlainToken($invitation),
        );
        tenancy()->end();

        $this->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/public/procurement/rfq-quotes/{$encoded}/bids", [
                'contact_name' => 'Vendor Contact',
                'lines' => [
                    ['rfq_line_id' => $lineId, 'quantity' => 2, 'unit_price' => 900, 'lead_time_days' => 10],
                ],
            ])
            ->assertCreated();

        $show = $this->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/public/procurement/rfq-quotes/{$encoded}");
        $show->assertOk()
            ->assertJsonPath('data.has_existing_bid', true)
            ->assertJsonPath('data.can_submit', true)
            ->assertJsonPath('data.can_revise', true);

        $revise = $this->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/public/procurement/rfq-quotes/{$encoded}/bids", [
                'contact_name' => 'Vendor Contact',
                'lines' => [
                    ['rfq_line_id' => $lineId, 'quantity' => 2, 'unit_price' => 850, 'lead_time_days' => 8],
                ],
            ]);
        $revise->assertCreated()
            ->assertJsonPath('data.bid.total_amount', 1700);

        $bidId = (string) $revise->json('data.bid.id');
        $versions = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}/bids/{$bidId}/versions");
        $versions->assertOk()
            ->assertJsonCount(2, 'data.versions');
    }

    public function test_expired_open_rfq_is_auto_closed(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');

        tenancy()->initialize($this->testTenant);
        $rfq = \App\Modules\ProcurementOne\Models\ProcurementRfq::query()->findOrFail($rfqId);
        $rfq->status = 'open';
        $rfq->bidding_closes_at = now()->subMinute();
        $rfq->save();
        tenancy()->end();

        tenancy()->initialize($this->testTenant);
        $result = app(\App\Modules\ProcurementOne\Services\ProcurementRfqAutoCloseService::class)->run();
        tenancy()->end();

        $this->assertSame(1, $result['rfqs_closed']);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}");
        $detail->assertOk()
            ->assertJsonPath('data.rfq.status', 'closed');
    }

    public function test_public_portal_accepts_monthly_yearly_quote_basis(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');
        $lineId = (string) $create->json('data.rfq.lines.0.id');

        tenancy()->initialize($this->testTenant);
        $prLine = \App\Modules\ProcurementOne\Models\ProcurementPrLine::query()
            ->where('pr_id', $prId)
            ->firstOrFail();
        $prLine->metadata_json = ['quote_basis' => 'monthly_yearly'];
        $prLine->save();
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $invitation = ProcurementRfqVendor::query()->where('rfq_id', $rfqId)->firstOrFail();
        $encoded = app(ProcurementRfqVendorInvitationService::class)->encodeAccessToken(
            $this->issuePlainToken($invitation),
        );
        tenancy()->end();

        $submit = $this->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/public/procurement/rfq-quotes/{$encoded}/bids", [
                'contact_name' => 'Vendor Contact',
                'lines' => [
                    [
                        'rfq_line_id' => $lineId,
                        'quantity' => 2,
                        'monthly_unit_price' => 120,
                        'yearly_unit_price' => 1200,
                        'lead_time_days' => 5,
                    ],
                ],
            ]);
        $submit->assertCreated()
            ->assertJsonPath('data.bid.total_amount_monthly', 240)
            ->assertJsonPath('data.bid.total_amount_yearly', 2400)
            ->assertJsonPath('data.bid.normalized_annual_amount', 2400);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}");
        $detail->assertOk()
            ->assertJsonPath('data.rfq.comparison_matrix.rows.0.total_amount_monthly', 240)
            ->assertJsonPath('data.rfq.comparison_matrix.rows.0.normalized_annual_amount', 2400);
    }

    public function test_internal_capture_accepts_monthly_yearly_quote_basis(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');
        $lineId = (string) $create->json('data.rfq.lines.0.id');

        tenancy()->initialize($this->testTenant);
        $prLine = \App\Modules\ProcurementOne\Models\ProcurementPrLine::query()
            ->where('pr_id', $prId)
            ->firstOrFail();
        $prLine->metadata_json = ['quote_basis' => 'monthly'];
        $prLine->save();
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk();

        $capture = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/bids", [
                'vendor_id' => $vendorId,
                'lines' => [
                    [
                        'rfq_line_id' => $lineId,
                        'quantity' => 2,
                        'monthly_unit_price' => 150,
                        'lead_time_days' => 3,
                    ],
                ],
            ]);
        $capture->assertCreated()
            ->assertJsonPath('data.bid.total_amount_monthly', 300)
            ->assertJsonPath('data.bid.normalized_annual_amount', 3600);

        $bidId = (string) $capture->json('data.bid.id');
        $versions = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}/bids/{$bidId}/versions");
        $versions->assertOk()
            ->assertJsonPath('data.versions.0.total_amount_monthly', 300)
            ->assertJsonPath('data.versions.0.lines.0.amount_monthly', 300)
            ->assertJsonPath('data.versions.0.lines.0.normalized_annual_amount', 3600);
    }

    public function test_vendor_inbox_lists_invitations(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorId],
            ]);
        $create->assertCreated();
        $rfqId = (string) $create->json('data.rfq.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $vendor = \App\Modules\ProcurementOne\Models\ProcurementVendor::query()->findOrFail($vendorId);
        [$plainInboxToken] = app(\App\Modules\ProcurementOne\Services\ProcurementVendorInboxTokenService::class)->ensureInboxUrl($vendor);
        $encoded = app(\App\Modules\ProcurementOne\Services\ProcurementVendorInboxTokenService::class)->encodeAccessToken($plainInboxToken);
        tenancy()->end();

        $show = $this->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/public/procurement/vendor-inbox/{$encoded}");
        $show->assertOk()
            ->assertJsonPath('data.vendor.id', $vendorId)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.rfq_id', $rfqId);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bootstrapApprovedPrWithVendor(): array
    {
        $this->createPurchaseRequisitionForm();
        tenancy()->initialize($this->testTenant);
        $vendorId = (string) ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-PORTAL',
            'company_name' => 'Portal Vendor',
            'tax_id' => 'VEND-PORTAL',
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => ['email' => 'vendor.portal@test.localhost'],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ])->id;
        tenancy()->end();

        $prId = $this->createApprovedPr([
            ['description' => 'Portal RFQ item', 'quantity' => 2, 'unit_price' => 1000],
        ]);

        return [$prId, $vendorId];
    }

    private function issuePlainToken(ProcurementRfqVendor $invitation): string
    {
        $secret = str_repeat('a', 48);
        $invitation->invitation_token_hash = hash('sha256', $secret);
        $invitation->invitation_token_expires_at = now()->addDay();
        $invitation->save();

        return (string) $invitation->id.'.'.$secret;
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Portal RFQ PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Portal test',
                'lines' => $lines,
            ]);
        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementPr::query()->find($prId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        return $prId;
    }

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'PR portal test',
                'status' => 'published',
                'metadata_json' => ['form_family' => 'purchase_requisition', 'use_approval_policy' => false],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1]],
            ]);
        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function approveSubmission(string $submissionId): void
    {
        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", ['decision' => 'approved'])
            ->assertOk();
    }
}
