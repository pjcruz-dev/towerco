<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Support\RolloutCoordinateRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolloutCoordinateRulesTest extends TestCase
{
    public function test_normalize_pair_swaps_when_latitude_out_of_range(): void
    {
        [$lat, $lng] = RolloutCoordinateRules::normalizePair(121.0437, 14.676);

        $this->assertEqualsWithDelta(14.676, $lat, 0.0001);
        $this->assertEqualsWithDelta(121.0437, $lng, 0.0001);
    }

    public function test_apply_to_input_requires_both_coordinates(): void
    {
        $this->expectException(ValidationException::class);

        RolloutCoordinateRules::applyToInput([
            'latitude' => 14.676,
        ]);
    }

    public function test_apply_to_input_passes_through_capture_metadata(): void
    {
        $result = RolloutCoordinateRules::applyToInput([
            'latitude' => 14.676,
            'longitude' => 121.0437,
            'coordinate_capture_method' => RolloutCoordinateRules::CAPTURE_GPS,
            'coordinate_accuracy_m' => 12.5,
        ]);

        $this->assertSame(14.676, $result['latitude']);
        $this->assertSame(121.0437, $result['longitude']);
        $this->assertSame(RolloutCoordinateRules::CAPTURE_GPS, $result['coordinate_capture_method']);
        $this->assertSame(12.5, $result['coordinate_accuracy_m']);
    }
}
