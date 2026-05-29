<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutEmailNotificationPolicyDefaults;
use App\Modules\Rollout\Data\RolloutEmailNotificationRecipients;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Services\RolloutEmailNotificationPolicyService;
use Tests\TestCase;

final class RolloutEmailNotificationPolicyServiceTest extends TestCase
{
    public function test_merged_policies_use_defaults_when_no_overrides(): void
    {
        $service = app(RolloutEmailNotificationPolicyService::class);

        $merged = $service->mergedPolicies(null);

        $this->assertTrue($merged['gate_approval']['enabled']);
        $this->assertTrue($merged['gate_approval']['events']['rejected']['enabled']);
    }

    public function test_master_switch_disables_all_gate_emails(): void
    {
        $service = app(RolloutEmailNotificationPolicyService::class);

        $config = new TenantRolloutPlaybookConfig([
            'email_notification_policies' => [
                'gate_approval' => [
                    'enabled' => false,
                    'events' => [],
                ],
            ],
        ]);

        $this->assertFalse($service->shouldNotifyGateEvent('submitted', $config));
    }

    public function test_event_can_be_disabled_individually(): void
    {
        $service = app(RolloutEmailNotificationPolicyService::class);

        $config = new TenantRolloutPlaybookConfig([
            'email_notification_policies' => [
                'gate_approval' => [
                    'enabled' => true,
                    'events' => [
                        'rejected' => [
                            'enabled' => false,
                            'recipients' => [RolloutEmailNotificationRecipients::REQUESTER],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($service->shouldNotifyGateEvent('rejected', $config));
        $this->assertTrue($service->shouldNotifyGateEvent('submitted', $config));
    }

    public function test_normalize_tenant_input_preserves_all_events(): void
    {
        $service = app(RolloutEmailNotificationPolicyService::class);

        $normalized = $service->normalizeTenantInput([
            'gate_approval' => [
                'enabled' => true,
                'events' => [
                    'approved' => [
                        'enabled' => true,
                        'recipients' => ['requester', 'pmo_owner'],
                    ],
                ],
            ],
        ]);

        foreach (RolloutEmailNotificationPolicyDefaults::GATE_EVENTS as $event) {
            $this->assertArrayHasKey($event, $normalized['gate_approval']['events']);
        }

        $this->assertSame(
            ['requester', 'pmo_owner'],
            $normalized['gate_approval']['events']['approved']['recipients'],
        );
    }
}
