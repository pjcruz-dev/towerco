<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Contracts\ReverseGeocoderInterface;
use App\Modules\Rollout\DTOs\ReverseGeocodeResult;
use App\Modules\Rollout\Services\ReverseGeocodeService;
use App\Modules\Rollout\Support\MapboxReverseGeocoder;
use App\Modules\Rollout\Support\NominatimReverseGeocoder;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ReverseGeocodeServiceTest extends TestCase
{
    public function test_auto_driver_uses_mapbox_when_token_present(): void
    {
        config([
            'geocoding.driver' => 'auto',
            'geocoding.mapbox.access_token' => 'pk.live-test-token',
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);

        $this->assertInstanceOf(MapboxReverseGeocoder::class, app(ReverseGeocoderInterface::class));
    }

    public function test_auto_driver_falls_back_to_nominatim_for_placeholder_token(): void
    {
        config([
            'geocoding.driver' => 'auto',
            'geocoding.mapbox.access_token' => 'pk....',
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);

        $this->assertInstanceOf(NominatimReverseGeocoder::class, app(ReverseGeocoderInterface::class));
    }

    public function test_auto_driver_falls_back_to_nominatim_without_token(): void
    {
        config([
            'geocoding.driver' => 'auto',
            'geocoding.mapbox.access_token' => '',
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);

        $this->assertInstanceOf(NominatimReverseGeocoder::class, app(ReverseGeocoderInterface::class));
    }

    public function test_mapbox_reverse_parses_place_name(): void
    {
        config([
            'geocoding.driver' => 'mapbox',
            'geocoding.mapbox.access_token' => 'pk.live-test-token',
            'geocoding.mapbox.base_url' => 'https://api.mapbox.com/geocoding/v5/mapbox.places',
            'geocoding.country' => 'ph',
        ]);

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [
                    [
                        'id' => 'address.1',
                        'place_name' => 'Rizal Park, Manila, Metro Manila, Philippines',
                        'text' => 'Rizal Park',
                        'place_type' => ['poi'],
                        'context' => [],
                    ],
                ],
            ]),
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);
        $this->app->instance(ReverseGeocoderInterface::class, app(MapboxReverseGeocoder::class));

        $result = app(ReverseGeocodeService::class)->reverse(14.5823, 120.9785);

        $this->assertSame('Rizal Park, Manila, Metro Manila, Philippines', $result->formattedAddress);
        $this->assertSame('mapbox', $result->provider);
    }

    public function test_mapbox_forward_parses_center(): void
    {
        config([
            'geocoding.driver' => 'mapbox',
            'geocoding.mapbox.access_token' => 'pk.live-test-token',
            'geocoding.mapbox.base_url' => 'https://api.mapbox.com/geocoding/v5/mapbox.places',
            'geocoding.country' => 'ph',
        ]);

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [
                    [
                        'id' => 'place.1',
                        'place_name' => 'Quezon City, Metro Manila, Philippines',
                        'center' => [121.0509, 14.6760],
                        'text' => 'Quezon City',
                        'place_type' => ['place'],
                        'context' => [],
                    ],
                ],
            ]),
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);
        $this->app->instance(ReverseGeocoderInterface::class, app(MapboxReverseGeocoder::class));

        $result = app(ReverseGeocodeService::class)->forward('Quezon City');

        $this->assertSame('Quezon City, Metro Manila, Philippines', $result->formattedAddress);
        $this->assertEqualsWithDelta(14.6760, $result->latitude, 0.0001);
        $this->assertEqualsWithDelta(121.0509, $result->longitude, 0.0001);
    }

    public function test_nominatim_reverse_parses_display_name(): void
    {
        config([
            'geocoding.driver' => 'nominatim',
            'geocoding.nominatim.base_url' => 'https://nominatim.openstreetmap.org',
            'geocoding.nominatim.user_agent' => 'TowerOS-Test/1.0',
            'geocoding.country' => 'ph',
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Ermita, Manila, Metro Manila, Philippines',
                'address' => [
                    'suburb' => 'Ermita',
                    'city' => 'Manila',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);
        $this->app->instance(ReverseGeocoderInterface::class, app(NominatimReverseGeocoder::class));

        $result = app(ReverseGeocodeService::class)->reverse(14.5823, 120.9785);

        $this->assertSame('Ermita, Manila, Metro Manila, Philippines', $result->formattedAddress);
        $this->assertSame('nominatim', $result->provider);
        $this->assertSame('Manila', $result->components['city'] ?? null);
    }

    public function test_nominatim_forward_parses_search_hit(): void
    {
        config([
            'geocoding.driver' => 'nominatim',
            'geocoding.nominatim.base_url' => 'https://nominatim.openstreetmap.org',
            'geocoding.nominatim.user_agent' => 'TowerOS-Test/1.0',
            'geocoding.country' => 'ph',
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Makati, Metro Manila, Philippines',
                    'lat' => '14.5547',
                    'lon' => '121.0244',
                    'address' => [
                        'city' => 'Makati',
                        'country' => 'Philippines',
                    ],
                ],
            ]),
        ]);

        $this->app->forgetInstance(ReverseGeocoderInterface::class);
        $this->app->instance(ReverseGeocoderInterface::class, app(NominatimReverseGeocoder::class));

        $result = app(ReverseGeocodeService::class)->forward('Makati');

        $this->assertSame('Makati, Metro Manila, Philippines', $result->formattedAddress);
        $this->assertEqualsWithDelta(14.5547, $result->latitude, 0.0001);
        $this->assertEqualsWithDelta(121.0244, $result->longitude, 0.0001);
    }

    public function test_throws_when_provider_returns_empty(): void
    {
        $geocoder = new class implements ReverseGeocoderInterface
        {
            public function reverse(float $latitude, float $longitude): ?ReverseGeocodeResult
            {
                return null;
            }

            public function forward(string $query): ?ReverseGeocodeResult
            {
                return null;
            }

            public function providerName(): string
            {
                return 'stub';
            }
        };

        $service = new ReverseGeocodeService($geocoder);

        $this->expectException(ValidationException::class);
        $service->reverse(14.5995, 120.9842);
    }
}
