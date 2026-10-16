<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicEntities;

use App\Models\TicketingTicket;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use App\Modules\DynamicEntities\Support\DocumentSeriesPack;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ManagedSeriesTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_ticket_series_continues_from_the_highest_number_and_can_skip_ahead(): void
    {
        tenancy()->initialize($this->testTenant);
        try {
            TicketingTicket::query()->create([
                'id' => (string) Str::uuid(),
                'ticket_number' => 7,
                'title' => 'Existing',
                'status' => TicketingTicket::STATUS_OPEN,
                'priority' => TicketingTicket::PRIORITY_NORMAL,
                'requester_id' => $this->testTenantAdmin->id,
            ]);

            $series = app(ManagedSeriesService::class);
            $series->flushCache();
            $this->assertSame(8, $series->issueTicketNumber());

            $record = $this->seriesRow(DocumentSeriesPack::SERIES, DocumentSeriesPack::TICKET_KEY);
            $values = $record->values_json ?? [];
            $values['last_number'] = 20;
            $values['series_prefix'] = 'HD';
            $values['number_format'] = '###';
            $record->values_json = $values;
            $record->save();
            $series->flushCache();

            $this->assertSame(21, $series->issueTicketNumber());
            $existing = TicketingTicket::query()->where('ticket_number', 7)->firstOrFail();
            $this->assertSame('HD-007', $existing->displayNumber());
            $this->assertSame('HD-021', TicketingTicket::query()->make(['ticket_number' => 21])->displayNumber());
        } finally {
            tenancy()->end();
        }
    }

    public function test_asset_series_issues_the_type_prefix_and_follows_an_edited_prefix(): void
    {
        tenancy()->initialize($this->testTenant);
        try {
            $series = app(ManagedSeriesService::class);
            $series->flushCache();

            $this->assertSame('ATC-LAP-000001', $series->issueAssetTag(AssetType::Laptop));
            $this->assertSame('ATC-LAP-000002', $series->issueAssetTag(AssetType::Laptop));
            $this->assertSame('ATC-DSK-000001', $series->issueAssetTag(AssetType::Desktop));

            $record = $this->seriesRow(DocumentSeriesPack::SERIES, 'LAP');
            $values = $record->values_json ?? [];
            $values['series_prefix'] = 'LAP';
            $record->values_json = $values;
            $record->save();
            $series->flushCache();

            $this->assertSame('LAP-000003', $series->issueAssetTag(AssetType::Laptop));
        } finally {
            tenancy()->end();
        }
    }

    private function seriesRow(string $slug, string $key): DynRecord
    {
        $entity = DynEntity::query()->where('slug', $slug)->firstOrFail();

        return DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('source_external_id', $key)
            ->firstOrFail();
    }
}
