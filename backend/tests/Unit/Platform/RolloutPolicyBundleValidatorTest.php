<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Services\RolloutPolicyBundleValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutPolicyBundleValidatorTest extends TestCase
{
    public function test_post_day_one_phases_must_match_sla_budget(): void
    {
        $validator = new RolloutPolicyBundleValidator();

        $this->expectException(ValidationException::class);

        $validator->validate([
            'bts' => [
                [
                    'phase_key' => 'moc_col',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 1,
                    'working_day_end' => 50,
                ],
                [
                    'phase_key' => 'construction',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 51,
                    'working_day_end' => 100,
                ],
            ],
        ], [
            'bts' => ['working_days' => 115],
        ]);
    }

    public function test_valid_post_day_one_total_passes(): void
    {
        $validator = new RolloutPolicyBundleValidator();

        $validator->validate([
            'rtb' => [
                [
                    'phase_key' => 'moc_col',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 1,
                    'working_day_end' => 40,
                ],
                [
                    'phase_key' => 'construction',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 41,
                    'working_day_end' => 85,
                ],
            ],
        ], [
            'rtb' => ['working_days' => 85],
        ]);

        $this->assertTrue(true);
    }

    public function test_phases_with_counts_toward_sla_false_are_excluded_from_sla_budget(): void
    {
        $validator = new RolloutPolicyBundleValidator();

        $validator->validate([
            'bts' => [
                [
                    'phase_key' => 'lgu_clearance',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 10,
                    'working_day_end' => 14,
                    'counts_toward_sla' => false,
                    'is_custom' => true,
                ],
                [
                    'phase_key' => 'moc_col',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 1,
                    'working_day_end' => 40,
                ],
                [
                    'phase_key' => 'construction',
                    'anchor' => 'tssr_approved',
                    'working_day_start' => 41,
                    'working_day_end' => 85,
                ],
            ],
        ], [
            'bts' => ['working_days' => 85],
        ]);

        $this->assertTrue(true);
    }
}
