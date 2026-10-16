<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Models\TicketingTicket;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetTagSequence;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DocumentSeriesPack;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Issues ticket numbers and asset tags from Document Series rows.
 * Prefix, number format, and last number used on those rows decide the next issue.
 */
final class ManagedSeriesService
{
    /** @var array<string, array{prefix: string, format: string}> */
    private static array $specCache = [];

    public function __construct(
        private readonly DynAutomaticIdService $formats,
    ) {}

    public function flushCache(): void
    {
        self::$specCache = [];
    }

    /**
     * @param  array{prefix: string, format: string}  $spec
     * @return array{prefix: string, format: string}
     */
    private function rememberSpec(string $name, array $spec): array
    {
        self::$specCache[$this->cacheKey($name)] = $spec;

        return $spec;
    }

    /**
     * @return array{prefix: string, format: string}|null
     */
    private function cachedSpec(string $name): ?array
    {
        return self::$specCache[$this->cacheKey($name)] ?? null;
    }

    private function forgetSpec(string $name): void
    {
        unset(self::$specCache[$this->cacheKey($name)]);
    }

    private function cacheKey(string $name): string
    {
        return (string) (tenant('id') ?? '').'|'.$name;
    }

    public function ensureTicket(): void
    {
        $carried = $this->carriedLast('ticket_series', 'ticket');
        $entity = $this->ensureDocumentSeries();
        $floor = max((int) TicketingTicket::query()->max('ticket_number'), $carried);
        $this->ensureRow($entity, DocumentSeriesPack::TICKET_KEY, 'TKT — Ticket', 'TKT', '#####', 'TKT', $floor);
        $this->dropSeries('ticket_series');
        $this->forgetSpec('ticket');
    }

    public function ensureAsset(): void
    {
        $entity = $this->ensureDocumentSeries();
        foreach (DocumentSeriesPack::operationalRows() as $row) {
            if (! is_string($row['asset'])) {
                continue;
            }
            $type = AssetType::parse($row['asset']);
            $carried = $this->carriedLast('asset_series', $type->value);
            $existing = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('source_external_id', $row['key'])
                ->first();
            $prefix = $type->tagPrefix();
            $floor = $existing instanceof DynRecord
                ? max($this->assetFloorForPrefix($this->specFrom($existing, [
                    'prefix' => $prefix,
                    'format' => $row['format'],
                ])['prefix']), $carried)
                : max($this->assetFloor($type), $carried);
            $this->ensureRow($entity, $row['key'], $row['title'], $prefix, $row['format'], $row['document_type'], $floor);
            unset(self::$specCache[$this->cacheKey('asset:'.$type->value)]);
        }
        $this->dropSeries('asset_series');
    }

    public function issueTicketNumber(): int
    {
        $this->ensureTicket();

        $run = function (): int {
            $record = $this->lockedRow(DocumentSeriesPack::SERIES, DocumentSeriesPack::TICKET_KEY);
            $max = (int) TicketingTicket::query()->max('ticket_number');
            $last = $this->lastNumber($record);
            $next = max($last, $max) + 1;
            $this->writeLast($record, $next);
            $this->forgetSpec('ticket');

            return $next;
        };

        if (DB::transactionLevel() > 0) {
            return $run();
        }

        return (int) DB::transaction($run);
    }

    public function issueAssetTag(AssetType $type): string
    {
        $this->ensureAsset();

        $run = function () use ($type): string {
            $record = $this->lockedRow(DocumentSeriesPack::SERIES, $this->assetKey($type));
            $spec = $this->specFrom($record, [
                'prefix' => $type->tagPrefix(),
                'format' => '######',
            ]);
            $sequence = $this->lockedSequence($spec['prefix']);
            $number = max((int) $sequence->next_number, $this->lastNumber($record) + 1);
            $sequence->next_number = $number + 1;
            $sequence->save();
            $this->writeLast($record, $number);
            unset(self::$specCache[$this->cacheKey('asset:'.$type->value)]);

            return $this->render($spec['prefix'], $spec['format'], $number);
        };

        if (DB::transactionLevel() > 0) {
            return $run();
        }

        return (string) DB::transaction($run);
    }

    public function formatTicket(int $number): string
    {
        $spec = $this->ticketSpec();

        return $this->render($spec['prefix'], $spec['format'], $number);
    }

    /**
     * @return array{prefix: string, format: string}
     */
    private function ticketSpec(): array
    {
        $cached = $this->cachedSpec('ticket');
        if ($cached !== null) {
            return $cached;
        }

        $defaults = ['prefix' => 'TKT', 'format' => '#####'];
        try {
            $entity = DynEntity::query()->where('slug', DocumentSeriesPack::SERIES)->first();
            if (! $entity instanceof DynEntity) {
                return $this->rememberSpec('ticket', $defaults);
            }
            $record = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('source_external_id', DocumentSeriesPack::TICKET_KEY)
                ->where('is_deleted', false)
                ->first();
            if (! $record instanceof DynRecord) {
                return $this->rememberSpec('ticket', $defaults);
            }

            return $this->rememberSpec('ticket', $this->specFrom($record, $defaults));
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * @param  array<string, mixed>  $beforeValues
     */
    public function afterTicketSeriesSaved(DynRecord $record, array $beforeValues): void
    {
        unset(self::$specCache[$this->cacheKey('ticket')]);
        $after = is_array($record->values_json) ? $record->values_json : [];
        $prefixChanged = (string) ($beforeValues['series_prefix'] ?? '') !== (string) ($after['series_prefix'] ?? '');
        $formatChanged = (string) ($beforeValues['number_format'] ?? '') !== (string) ($after['number_format'] ?? '');
        if (! $prefixChanged && ! $formatChanged) {
            return;
        }

        $this->refreshTicketDisplays();
    }

    public function render(string $prefix, string $format, int $number, ?CarbonInterface $now = null): string
    {
        $format = trim($format) !== '' ? trim($format) : '#####';
        $body = $this->formats->applyFormat($format, $number, $now ?? now());
        $prefix = trim($prefix);
        if ($prefix === '') {
            return $body;
        }
        if (str_ends_with($prefix, '-') || str_starts_with($body, '-')) {
            return $prefix.$body;
        }

        return $prefix.'-'.$body;
    }

    private function refreshTicketDisplays(): void
    {
        $helpdesk = DynEntity::query()->where('slug', TicketingSchemaService::SLUG)->first();
        if (! $helpdesk instanceof DynEntity) {
            return;
        }

        $spec = $this->ticketSpec();
        DynRecord::query()
            ->where('entity_id', $helpdesk->id)
            ->where('is_deleted', false)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($spec): void {
                foreach ($rows as $row) {
                    if (! $row instanceof DynRecord) {
                        continue;
                    }
                    $ticket = TicketingTicket::query()->find($row->id);
                    if (! $ticket instanceof TicketingTicket) {
                        continue;
                    }
                    $display = $this->render($spec['prefix'], $spec['format'], (int) $ticket->ticket_number);
                    $values = is_array($row->values_json) ? $row->values_json : [];
                    if ((string) ($values['ticket_number'] ?? '') === $display) {
                        continue;
                    }
                    $values['ticket_number'] = $display;
                    $row->values_json = $values;
                    $row->save();
                }
            });
    }

    private function assetFloorForPrefix(string $prefix): int
    {
        $sequence = AssetTagSequence::query()->where('prefix', $prefix)->value('next_number');

        return $sequence !== null ? max(0, (int) $sequence - 1) : 0;
    }

    private function assetFloor(AssetType $type): int
    {
        $fromSequence = $this->assetFloorForPrefix($type->tagPrefix());
        $fromTags = 0;
        foreach (Asset::query()->where('asset_type', $type->value)->pluck('asset_tag') as $tag) {
            if (preg_match('/(\d+)$/', (string) $tag, $matches) === 1) {
                $fromTags = max($fromTags, (int) $matches[1]);
            }
        }

        return max($fromSequence, $fromTags);
    }

    private function ensureEntity(string $slug, string $name, string $description, int $sortOrder): DynEntity
    {
        $entity = DynEntity::query()->where('slug', $slug)->first();
        if ($entity instanceof DynEntity) {
            return $entity;
        }

        try {
            return DynEntity::query()->create([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'module_pack' => DynModulePack::REFERENCE,
                'storage_mode' => 'json',
                'is_location_based' => false,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        } catch (QueryException) {
            return DynEntity::query()->where('slug', $slug)->firstOrFail();
        }
    }

    private function ensureDocumentSeries(): DynEntity
    {
        $entity = $this->ensureEntity(
            DocumentSeriesPack::SERIES,
            'Document Series',
            'Prefix, document type, and number format. Ticket and asset numbers are rows in this list.',
            50,
        );
        $this->ensureFields($entity);
        $this->syncOperationalChoices($entity);
        DynField::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'last_number')
            ->update(['show_in_table' => true]);

        return $entity;
    }

    private function syncOperationalChoices(DynEntity $entity): void
    {
        $field = DynField::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'document_type')
            ->first();
        if (! $field instanceof DynField) {
            return;
        }

        $options = is_array($field->options_json) ? $field->options_json : [];
        $choices = is_array($options['choices'] ?? null) ? $options['choices'] : [];
        $known = [];
        foreach ($choices as $choice) {
            if (is_array($choice) && isset($choice['value'])) {
                $known[(string) $choice['value']] = true;
            }
        }
        foreach (DocumentSeriesPack::seriesFields() as $def) {
            if ($def['name'] !== 'document_type' || ! is_array($def['options_json']['choices'] ?? null)) {
                continue;
            }
            foreach ($def['options_json']['choices'] as $choice) {
                if (! is_array($choice) || isset($known[(string) ($choice['value'] ?? '')])) {
                    continue;
                }
                $choices[] = $choice;
                $known[(string) $choice['value']] = true;
            }
        }
        $options['choices'] = $choices;
        $field->options_json = $options;
        $field->save();
    }

    private function carriedLast(string $slug, string $key): int
    {
        $entity = DynEntity::query()->where('slug', $slug)->first();
        if (! $entity instanceof DynEntity) {
            return 0;
        }
        $record = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('source_external_id', $key)
            ->first();

        return $record instanceof DynRecord ? $this->lastNumber($record) : 0;
    }

    private function dropSeries(string $slug): void
    {
        DynEntity::query()->where('slug', $slug)->delete();
    }

    private function assetKey(AssetType $type): string
    {
        foreach (DocumentSeriesPack::operationalRows() as $row) {
            if ($row['asset'] === $type->value) {
                return $row['key'];
            }
        }

        return strtoupper($type->value);
    }

    private function subsidiaryId(string $code): ?string
    {
        $subsidiaries = DynEntity::query()->where('slug', DocumentSeriesPack::SUBSIDIARIES)->first();
        if (! $subsidiaries instanceof DynEntity) {
            return null;
        }
        $record = DynRecord::query()
            ->where('entity_id', $subsidiaries->id)
            ->where('source_external_id', $code)
            ->where('is_deleted', false)
            ->first();

        return $record instanceof DynRecord ? (string) $record->id : null;
    }

    private function ensureFields(DynEntity $entity): void
    {
        $subsidiaries = DynEntity::query()->where('slug', DocumentSeriesPack::SUBSIDIARIES)->first();
        foreach (DocumentSeriesPack::seriesFields() as $def) {
            $target = null;
            if (($def['target_slug'] ?? null) === DocumentSeriesPack::SUBSIDIARIES && $subsidiaries instanceof DynEntity) {
                $target = (string) $subsidiaries->id;
            }
            $existing = DynField::query()
                ->where('entity_id', $entity->id)
                ->where('name', $def['name'])
                ->first();
            if ($existing instanceof DynField) {
                if ($target !== null && $existing->target_entity_id === null) {
                    $existing->target_entity_id = $target;
                    $existing->save();
                }

                continue;
            }

            DynField::query()->create([
                'entity_id' => $entity->id,
                'name' => $def['name'],
                'label' => $def['label'],
                'type' => $def['type'],
                'is_required' => (bool) ($def['is_required'] ?? false),
                'is_key' => (bool) ($def['is_key'] ?? false),
                'is_system_field' => (bool) ($def['is_system_field'] ?? false),
                'system_column' => ($def['is_system_field'] ?? false) ? $def['name'] : null,
                'show_in_table' => (bool) ($def['show_in_table'] ?? false),
                'is_filterable' => (bool) ($def['is_filterable'] ?? false),
                'options_json' => $def['options_json'] ?? null,
                'target_entity_id' => $target,
                'column_span' => 6,
                'field_order' => (int) $def['field_order'],
            ]);
        }
    }

    private function ensureRow(
        DynEntity $entity,
        string $key,
        string $name,
        string $prefix,
        string $format,
        string $documentType,
        int $floor,
    ): void {
        $existing = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('source_external_id', $key)
            ->first();
        if ($existing instanceof DynRecord) {
            if ($existing->is_deleted) {
                $existing->is_deleted = false;
                $existing->deleted_at = null;
                $existing->save();
            }
            $stored = $this->lastNumber($existing);
            if ($floor > $stored) {
                $this->writeLast($existing, $floor);
            }

            return;
        }

        DynRecord::query()->create([
            'entity_id' => $entity->id,
            'title' => $name,
            'source_external_id' => $key,
            'status' => 'Active',
            'is_deleted' => false,
            'values_json' => [
                'status' => 'Active',
                'subsidiary_id' => $this->subsidiaryId('ATC'),
                'document_type' => $documentType,
                'series_prefix' => $prefix,
                'number_format' => $format,
                'last_number' => $floor,
            ],
        ]);
    }

    private function lockedRow(string $slug, string $key): DynRecord
    {
        $entity = DynEntity::query()->where('slug', $slug)->first();
        if (! $entity instanceof DynEntity) {
            throw ValidationException::withMessages([
                'series' => [__('The number series is not set up yet.')],
            ]);
        }

        $record = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('source_external_id', $key)
            ->where('is_deleted', false)
            ->lockForUpdate()
            ->first();
        if (! $record instanceof DynRecord) {
            throw ValidationException::withMessages([
                'series' => [__('The number series is not set up yet.')],
            ]);
        }

        return $record;
    }

    private function lockedSequence(string $prefix): AssetTagSequence
    {
        $sequence = AssetTagSequence::query()->where('prefix', $prefix)->lockForUpdate()->first();
        if ($sequence instanceof AssetTagSequence) {
            return $sequence;
        }

        AssetTagSequence::query()->create([
            'id' => (string) Str::uuid(),
            'prefix' => $prefix,
            'next_number' => 1,
        ]);
        $sequence = AssetTagSequence::query()->where('prefix', $prefix)->lockForUpdate()->first();
        if (! $sequence instanceof AssetTagSequence) {
            throw ValidationException::withMessages([
                'asset_tag' => [__('Could not issue an asset tag. Try again.')],
            ]);
        }

        return $sequence;
    }

    private function lastNumber(DynRecord $record): int
    {
        $values = is_array($record->values_json) ? $record->values_json : [];

        return (int) ($values['last_number'] ?? 0);
    }

    private function writeLast(DynRecord $record, int $last): void
    {
        $values = is_array($record->values_json) ? $record->values_json : [];
        $values['last_number'] = $last;
        $record->values_json = $values;
        $record->save();
    }

    /**
     * @param  array{prefix: string, format: string}  $defaults
     * @return array{prefix: string, format: string}
     */
    private function specFrom(DynRecord $record, array $defaults): array
    {
        $values = is_array($record->values_json) ? $record->values_json : [];
        $prefix = trim((string) ($values['series_prefix'] ?? ''));
        $format = trim((string) ($values['number_format'] ?? ''));

        return [
            'prefix' => $prefix !== '' ? $prefix : $defaults['prefix'],
            'format' => $format !== '' ? $format : $defaults['format'],
        ];
    }
}
