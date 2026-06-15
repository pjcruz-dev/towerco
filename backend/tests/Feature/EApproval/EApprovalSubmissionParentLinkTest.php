<?php



declare(strict_types=1);



namespace Tests\Feature\EApproval;



use App\Core\Http\Middleware\EnsureActiveSession;

use App\Core\Http\Middleware\EnsureMfaVerified;

use App\Modules\Identity\Models\TenantUser;

use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;

use Tests\TestCase;



final class EApprovalSubmissionParentLinkTest extends TestCase

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



    public function test_liquidation_submission_links_to_cash_advance_parent(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

                ['type' => 'textarea', 'name' => 'purpose', 'label' => 'Purpose', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

            'purpose' => 'Site survey travel',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'text', 'name' => 'cash_advance_document_no', 'label' => 'CA document no.', 'validation' => ['required' => true]],

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

                ['type' => 'date', 'name' => 'liquidation_date', 'label' => 'Liquidation date', 'validation' => ['required' => true]],

                ['type' => 'textarea', 'name' => 'notes', 'label' => 'Notes'],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $liqRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '1200',

                ],

            ]);



        $liqRes->assertCreated();

        $liqRes->assertJsonPath('data.parent_submission_id', $caSubmissionId);



        $caDocumentNo = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson("/api/v1/e-approval/submissions/{$caSubmissionId}")

            ->json('data.document_no');



        $caRef = collect($liqRes->json('data.values'))

            ->firstWhere('field_name', 'cash_advance_document_no');



        $this->assertSame($caDocumentNo, $caRef['value'] ?? null);

        $notes = collect($liqRes->json('data.values'))

            ->firstWhere('field_name', 'notes');

        $this->assertSame('Site survey travel', $notes['value'] ?? null);

        $liquidationDate = collect($liqRes->json('data.values'))

            ->firstWhere('field_name', 'liquidation_date');

        $this->assertNotEmpty($liquidationDate['value'] ?? null);

    }



    public function test_open_cash_advances_include_prefill_values_for_child_form(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

                ['type' => 'textarea', 'name' => 'purpose', 'label' => 'Purpose', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '2500',

            'purpose' => 'Field kit replenishment',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'text', 'name' => 'cash_advance_document_no', 'label' => 'CA document no.', 'validation' => ['required' => true]],

                ['type' => 'date', 'name' => 'liquidation_date', 'label' => 'Liquidation date', 'validation' => ['required' => true]],

                ['type' => 'textarea', 'name' => 'notes', 'label' => 'Notes'],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $caDocumentNo = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson("/api/v1/e-approval/submissions/{$caSubmissionId}")

            ->json('data.document_no');



        $response = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson("/api/v1/e-approval/cash-advances/open?for_form_id={$liqFormId}");



        $response->assertOk();

        $response->assertJsonPath('data.items.0.id', $caSubmissionId);

        $response->assertJsonPath('data.items.0.prefill_values.cash_advance_document_no', $caDocumentNo);

        $response->assertJsonPath('data.items.0.prefill_values.notes', 'Field kit replenishment');

        $this->assertNotEmpty($response->json('data.items.0.prefill_values.liquidation_date'));

    }



    public function test_draft_update_rejects_amount_over_open_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $draftRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'as_draft' => true,

                'values' => [

                    'total_reimbursement' => '1000',

                ],

            ]);



        $draftRes->assertCreated();

        $draftId = (string) $draftRes->json('data.id');



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->putJson("/api/v1/e-approval/submissions/{$draftId}/draft", [

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '6000',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['total_reimbursement']);

    }



    public function test_resubmit_rejects_amount_over_open_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $liqRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '1000',

                ],

            ]);



        $liqRes->assertCreated();

        $liqSubmissionId = (string) $liqRes->json('data.id');

        $this->rejectSubmission($liqSubmissionId);



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->putJson("/api/v1/e-approval/submissions/{$liqSubmissionId}/resubmit", [

                'values' => [

                    'total_reimbursement' => '6000',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['total_reimbursement']);

    }



    public function test_submission_detail_includes_related_parent_and_children(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $liqRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '1200',

                ],

            ]);



        $liqRes->assertCreated();

        $liqSubmissionId = (string) $liqRes->json('data.id');



        $childDetail = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson("/api/v1/e-approval/submissions/{$liqSubmissionId}");



        $childDetail->assertOk();

        $childDetail->assertJsonPath('data.related_submissions.parent.id', $caSubmissionId);

        $childDetail->assertJsonPath('data.related_submissions.parent.relationship', 'parent');

        $childDetail->assertJsonPath('data.related_submissions.parent.amount_value', '5000');

        $childDetail->assertJsonPath('data.related_submissions.children', []);



        $parentDetail = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson("/api/v1/e-approval/submissions/{$caSubmissionId}");



        $parentDetail->assertOk();

        $parentDetail->assertJsonPath('data.related_submissions.parent', null);

        $parentDetail->assertJsonPath('data.related_submissions.children.0.id', $liqSubmissionId);

        $parentDetail->assertJsonPath('data.related_submissions.children.0.relationship', 'child');

        $parentDetail->assertJsonPath('data.related_submissions.children.0.amount_value', '1200');

    }



    public function test_liquidation_requires_parent_submission(): void

    {

        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'values' => [

                    'total_reimbursement' => '500',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['parent_submission_id']);

    }



    public function test_liquidation_rejects_pending_cash_advance_parent(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '1200',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['parent_submission_id']);

    }



    public function test_liquidation_rejects_amount_over_open_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '6000',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['total_reimbursement']);

    }



    public function test_liquidation_respects_cumulative_open_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '1200',

                ],

            ])

            ->assertCreated();



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '4000',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['total_reimbursement']);

    }



    public function test_parent_link_rejects_different_requestor(): void

    {

        tenancy()->initialize($this->testTenant);

        $otherUser = TenantUser::query()->create([

            'name' => 'Other Requestor',

            'email' => 'other-requestor@test.localhost',

            'password' => 'password',

        ]);

        $otherUser->assignRole('tenant_admin');

        tenancy()->end();



        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '1000',

        ], $otherUser);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '500',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['parent_submission_id']);

    }



    public function test_parent_link_rejects_wrong_parent_form_family(): void

    {

        $genericFormId = $this->createPublishedForm(

            'Generic request',

            [

                ['type' => 'text', 'name' => 'summary', 'label' => 'Summary', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'general'],

        );



        $parentId = $this->createSubmission($genericFormId, [

            'summary' => 'Not a cash advance',

        ]);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $parentId,

                'values' => [

                    'total_reimbursement' => '500',

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['parent_submission_id']);

    }



    public function test_open_cash_advances_lists_approved_parents_with_positive_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $response = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson('/api/v1/e-approval/cash-advances/open');



        $response->assertOk();

        $response->assertJsonPath('data.items.0.id', $caSubmissionId);

        $response->assertJsonPath('data.items.0.open_balance', 5000);

    }



    public function test_exact_open_balance_liquidation_is_allowed(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '5000',

                ],

            ])

            ->assertCreated();

    }



    public function test_open_balance_reflects_pending_liquidations(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '2000',

                ],

            ])

            ->assertCreated();



        $response = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson('/api/v1/e-approval/cash-advances/open');



        $response->assertOk();

        $response->assertJsonPath('data.items.0.id', $caSubmissionId);

        $response->assertJsonPath('data.items.0.open_balance', 3000);

    }



    public function test_rejected_liquidation_restores_open_balance_for_subsequent_liquidation(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $firstLiqRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '4000',

                ],

            ]);



        $firstLiqRes->assertCreated();

        $this->rejectSubmission((string) $firstLiqRes->json('data.id'));



        $openResponse = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->getJson('/api/v1/e-approval/cash-advances/open');



        $openResponse->assertOk();

        $openResponse->assertJsonPath('data.items.0.open_balance', 5000);



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '4500',

                ],

            ])

            ->assertCreated();

    }



    public function test_submit_draft_rejects_amount_over_open_balance(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '5000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

                ['type' => 'date', 'name' => 'liquidation_date', 'label' => 'Liquidation date', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $draftRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'as_draft' => true,

                'values' => [

                    'total_reimbursement' => '1000',

                ],

            ]);



        $draftRes->assertCreated();

        $draftId = (string) $draftRes->json('data.id');



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson("/api/v1/e-approval/submissions/{$draftId}/submit", [

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'total_reimbursement' => '6000',

                    'liquidation_date' => now()->toDateString(),

                ],

            ])

            ->assertUnprocessable()

            ->assertJsonValidationErrors(['total_reimbursement']);

    }



    public function test_reimbursement_submits_without_parent_link(): void

    {

        $reimbFormId = $this->createPublishedForm(

            'Reimbursement',

            [

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'reimbursement'],

        );



        $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $reimbFormId,

                'values' => [

                    'total_reimbursement' => '750',

                ],

            ])

            ->assertCreated()

            ->assertJsonPath('data.parent_submission_id', null);

    }



    public function test_parent_prefill_does_not_overwrite_user_document_no(): void

    {

        $caFormId = $this->createPublishedForm(

            'Cash advance',

            [

                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'cash_advance'],

        );



        $caSubmissionId = $this->createSubmission($caFormId, [

            'requested_amount' => '3000',

        ]);

        $this->approveSubmission($caSubmissionId);



        $liqFormId = $this->createPublishedForm(

            'Liquidation',

            [

                ['type' => 'text', 'name' => 'cash_advance_document_no', 'label' => 'CA document no.', 'validation' => ['required' => true]],

                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],

            ],

            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],

        );



        $liqRes = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $liqFormId,

                'parent_submission_id' => $caSubmissionId,

                'values' => [

                    'cash_advance_document_no' => 'USER-REF-001',

                    'total_reimbursement' => '500',

                ],

            ]);



        $liqRes->assertCreated();



        $caRef = collect($liqRes->json('data.values'))

            ->firstWhere('field_name', 'cash_advance_document_no');



        $this->assertSame('USER-REF-001', $caRef['value'] ?? null);

    }



    /**

     * @param  list<array<string, mixed>>  $fields

     * @param  array<string, mixed>  $metadata

     */

    private function createPublishedForm(string $name, array $fields, array $metadata = []): string

    {

        $response = $this->actingAsTenantAdmin()

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/forms', [

                'name' => $name,

                'description' => 'Parent link test',

                'status' => 'published',

                'metadata_json' => $metadata,

                'fields' => $fields,

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

    private function createSubmission(string $formId, array $values, ?TenantUser $actor = null): string

    {

        $actor ??= $this->testTenantAdmin;



        $response = $this->actingAs($actor)

            ->withHeaders($this->tenantApiHeaders())

            ->postJson('/api/v1/e-approval/submissions', [

                'form_id' => $formId,

                'values' => $values,

            ]);



        $response->assertCreated();



        return (string) $response->json('data.id');

    }



    private function approveSubmission(string $submissionId, ?TenantUser $approver = null): void

    {

        $this->decideSubmission($submissionId, 'approved', $approver);

    }



    private function rejectSubmission(string $submissionId, ?TenantUser $approver = null): void

    {

        $this->decideSubmission($submissionId, 'rejected', $approver);

    }



    private function decideSubmission(string $submissionId, string $decision, ?TenantUser $approver = null): void

    {

        $approver ??= $this->testTenantAdmin;



        $inbox = $this->actingAs($approver, 'sanctum')

            ->withHeaders($this->tenantApiHeaders())

            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');



        $inbox->assertOk();



        $approvalId = collect($inbox->json('data'))

            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');



        $this->assertNotEmpty($approvalId);



        $payload = ['decision' => $decision];
        if ($decision === 'rejected') {
            $payload['remarks'] = 'Rejected for test';
        }



        $this->actingAs($approver, 'sanctum')

            ->withHeaders($this->tenantApiHeaders())

            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", $payload)

            ->assertOk();

    }

}

