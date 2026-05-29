<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutBatchApiTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_batch_create_returns_parent_and_children(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollout-batches', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'batch_label' => 'NCR Batch Q2',
                'sites' => [
                    ['search_ring_name' => 'Ring Alpha'],
                    ['search_ring_name' => 'Ring Beta'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent.status', 'batch')
            ->assertJsonCount(2, 'data.children');

        tenancy()->initialize($this->testTenant);
        $parent = RolloutProgram::query()->findOrFail($response->json('data.parent.id'));
        $children = RolloutProgram::query()->where('parent_rollout_id', $parent->id)->get();
        $this->assertCount(2, $children);
        $this->assertTrue($children->every(static fn (RolloutProgram $child): bool => $child->status === 'saq'));
        tenancy()->end();
    }
}
