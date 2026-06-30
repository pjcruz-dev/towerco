<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Services\AssetDeployService;
use App\Modules\AssetOne\Services\AssetShowService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementInventoryLocation;
use App\Modules\ProcurementOne\Models\ProcurementInventoryStockBalance;
use App\Modules\ProcurementOne\Models\ProcurementInventoryStockMovement;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementInventoryMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProcurementInventoryStockService
{
    public function __construct(
        private readonly ProcurementInventoryPolicyService $policy,
        private readonly ProcurementInventoryLocationService $locations,
        private readonly AssetDeployService $assetDeploy,
    ) {}

    public static function stockKeyForPoLine(string $poLineId): string
    {
        return 'po_line:'.$poLineId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recordPostedGrn(ProcurementGrn $grn, TenantUser $actor): array
    {
        if (! $this->policy->isSimpleModeEnabled()) {
            return [];
        }

        $location = $this->resolveReceiptLocation($grn);
        if (! $location instanceof ProcurementInventoryLocation) {
            return [];
        }

        $movements = [];
        foreach ($grn->lines as $line) {
            $qty = (float) $line->quantity_received;
            if ($qty <= 0) {
                continue;
            }

            $movements[] = $this->asMovementPayload(
                $this->recordInbound(
                    location: $location,
                    poLineId: (string) $line->po_line_id,
                    description: (string) $line->description,
                    uom: $line->uom,
                    quantity: $qty,
                    movementType: ProcurementInventoryMovementType::GRN_RECEIPT,
                    actor: $actor,
                    grnId: (string) $grn->id,
                    grnLineId: (string) $line->id,
                    notes: $line->line_notes,
                ),
            );
        }

        return $movements;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{transfer_batch_id: string, movements: list<array<string, mixed>>}
     */
    public function transfer(array $input, TenantUser $actor): array
    {
        $this->assertInventoryEnabled();

        $from = $this->locations->find((string) $input['from_location_id']);
        $to = $this->locations->find((string) $input['to_location_id']);
        abort_if($from === null || $to === null, 422, __('Source or destination location was not found.'));
        abort_if((string) $from->id === (string) $to->id, 422, __('Transfer source and destination must differ.'));

        $poLineId = (string) $input['po_line_id'];
        $quantity = (float) $input['quantity'];
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('Transfer quantity must be greater than zero.')],
            ]);
        }

        $poLine = ProcurementPoLine::query()->find($poLineId);
        abort_if($poLine === null, 422, __('PO line not found.'));

        $stockKey = self::stockKeyForPoLine($poLineId);

        return DB::connection('tenant')->transaction(function () use ($from, $to, $poLine, $quantity, $stockKey, $actor, $input): array {
            $balance = $this->balanceFor($from, $stockKey);
            if ($balance === null || (float) $balance->quantity_on_hand + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => [__('Insufficient stock at the source location.')],
                ]);
            }

            $batchId = (string) Str::uuid();
            $notes = isset($input['notes']) ? (string) $input['notes'] : null;

            $out = $this->recordOutbound(
                location: $from,
                counterparty: $to,
                poLineId: (string) $poLine->id,
                description: (string) $poLine->description,
                uom: $poLine->uom,
                quantity: $quantity,
                movementType: ProcurementInventoryMovementType::TRANSFER_OUT,
                actor: $actor,
                transferBatchId: $batchId,
                notes: $notes,
            );

            $in = $this->recordInbound(
                location: $to,
                poLineId: (string) $poLine->id,
                description: (string) $poLine->description,
                uom: $poLine->uom,
                quantity: $quantity,
                movementType: ProcurementInventoryMovementType::TRANSFER_IN,
                actor: $actor,
                counterpartyLocationId: (string) $from->id,
                transferBatchId: $batchId,
                notes: $notes,
            );

            return [
                'transfer_batch_id' => $batchId,
                'movements' => [
                    $this->asMovementPayload($out),
                    $this->asMovementPayload($in),
                ],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{movement: array<string, mixed>, asset: array<string, mixed>|null}
     */
    public function deploy(array $input, TenantUser $actor): array
    {
        $this->assertInventoryEnabled();

        $from = $this->locations->find((string) $input['from_location_id']);
        $to = $this->locations->find((string) $input['to_location_id']);
        abort_if($from === null || $to === null, 422, __('Source or destination location was not found.'));

        $poLineId = (string) $input['po_line_id'];
        $quantity = (float) $input['quantity'];
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('Deploy quantity must be greater than zero.')],
            ]);
        }

        $stockKey = self::stockKeyForPoLine($poLineId);

        $poLine = ProcurementPoLine::query()->find($poLineId);
        abort_if($poLine === null, 422, __('PO line not found.'));

        return DB::connection('tenant')->transaction(function () use ($from, $to, $poLine, $quantity, $stockKey, $actor, $input): array {
            $balance = $this->balanceFor($from, $stockKey);
            if ($balance === null || (float) $balance->quantity_on_hand + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => [__('Insufficient stock at the source warehouse.')],
                ]);
            }

            $movement = $this->recordOutbound(
                location: $from,
                counterparty: $to,
                poLineId: (string) $poLine->id,
                description: (string) $poLine->description,
                uom: $poLine->uom,
                quantity: $quantity,
                movementType: ProcurementInventoryMovementType::DEPLOY,
                actor: $actor,
                notes: isset($input['notes']) ? (string) $input['notes'] : null,
                metadata: ['deploy_to_location_id' => (string) $to->id],
            );

            $assetPayload = null;
            $createAsset = (bool) ($input['create_asset'] ?? $this->policy->policy()['auto_create_assets_on_deploy']);
            if ($createAsset) {
                $asset = $this->assetDeploy->createFromDeploy(
                    poLine: $poLine,
                    destination: $to,
                    quantity: $quantity,
                    actor: $actor,
                    overrides: is_array($input['asset'] ?? null) ? $input['asset'] : [],
                    movementId: (string) $movement->id,
                );
                $movement->asset_id = (string) $asset->id;
                $movement->save();
                $assetPayload = app(AssetShowService::class)->asDetail($asset);
            }

            return [
                'movement' => $this->asMovementPayload($movement->refresh()->load(['location', 'counterpartyLocation', 'createdBy'])),
                'asset' => $assetPayload,
            ];
        });
    }

    public function paginateBalances(
        int $page,
        int $perPage,
        ?string $locationId = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = ProcurementInventoryStockBalance::query()
            ->with(['location:id,code,name,location_kind', 'poLine:id,description,uom'])
            ->where('quantity_on_hand', '>', 0)
            ->orderByDesc('updated_at');

        if ($locationId !== null && $locationId !== '' && $locationId !== 'all') {
            $query->where('location_id', $locationId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('description', 'like', $like)
                    ->orWhere('stock_key', 'like', $like);
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateMovements(
        int $page,
        int $perPage,
        ?string $locationId = null,
        ?string $grnId = null,
        ?string $movementType = null,
    ): LengthAwarePaginator {
        $query = ProcurementInventoryStockMovement::query()
            ->with(['location:id,code,name', 'counterpartyLocation:id,code,name', 'createdBy:id,name', 'asset:id,asset_code,name'])
            ->orderByDesc('created_at');

        if ($locationId !== null && $locationId !== '' && $locationId !== 'all') {
            $query->where('location_id', $locationId);
        }

        if ($grnId !== null && $grnId !== '') {
            $query->where('grn_id', $grnId);
        }

        if ($movementType !== null && $movementType !== '' && $movementType !== 'all') {
            $query->where('movement_type', $movementType);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movementsForGrn(string $grnId): array
    {
        return ProcurementInventoryStockMovement::query()
            ->with(['location:id,code,name', 'createdBy:id,name'])
            ->where('grn_id', $grnId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProcurementInventoryStockMovement $row) => $this->asMovementPayload($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceAsPayload(ProcurementInventoryStockBalance $balance): array
    {
        return [
            'id' => (string) $balance->id,
            'location_id' => (string) $balance->location_id,
            'location' => $balance->location ? [
                'id' => (string) $balance->location->id,
                'code' => $balance->location->code,
                'name' => $balance->location->name,
                'location_kind' => $balance->location->location_kind,
            ] : null,
            'po_line_id' => $balance->po_line_id,
            'stock_key' => $balance->stock_key,
            'description' => $balance->description,
            'uom' => $balance->uom,
            'quantity_on_hand' => (float) $balance->quantity_on_hand,
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asMovementPayload(ProcurementInventoryStockMovement $movement): array
    {
        return [
            'id' => (string) $movement->id,
            'movement_type' => $movement->movement_type,
            'movement_type_label' => ProcurementInventoryMovementType::label((string) $movement->movement_type),
            'transfer_batch_id' => $movement->transfer_batch_id,
            'location_id' => (string) $movement->location_id,
            'location' => $movement->location ? [
                'id' => (string) $movement->location->id,
                'code' => $movement->location->code,
                'name' => $movement->location->name,
            ] : null,
            'counterparty_location_id' => $movement->counterparty_location_id,
            'counterparty_location' => $movement->counterpartyLocation ? [
                'id' => (string) $movement->counterpartyLocation->id,
                'code' => $movement->counterpartyLocation->code,
                'name' => $movement->counterpartyLocation->name,
            ] : null,
            'grn_id' => $movement->grn_id,
            'grn_line_id' => $movement->grn_line_id,
            'po_line_id' => $movement->po_line_id,
            'asset_id' => $movement->asset_id,
            'asset' => $movement->asset ? [
                'id' => (string) $movement->asset->id,
                'asset_code' => $movement->asset->asset_code,
                'name' => $movement->asset->name,
            ] : null,
            'stock_key' => $movement->stock_key,
            'description' => $movement->description,
            'uom' => $movement->uom,
            'quantity' => (float) $movement->quantity,
            'notes' => $movement->notes,
            'metadata' => $movement->metadata_json ?? [],
            'created_by' => $movement->createdBy ? [
                'id' => (string) $movement->createdBy->id,
                'name' => $movement->createdBy->name,
            ] : null,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }

    private function resolveReceiptLocation(ProcurementGrn $grn): ?ProcurementInventoryLocation
    {
        if ($grn->inventory_location_id !== null) {
            $explicit = $this->locations->find((string) $grn->inventory_location_id);
            if ($explicit instanceof ProcurementInventoryLocation && $explicit->is_active) {
                return $explicit;
            }
        }

        return $this->locations->defaultReceiptLocation();
    }

    private function assertInventoryEnabled(): void
    {
        if (! $this->policy->isSimpleModeEnabled()) {
            throw ValidationException::withMessages([
                'inventory' => [__('Simple inventory is not enabled for this tenant.')],
            ]);
        }

        app(ProcurementOnePlanFeaturesService::class)->assertInventoryEnabled();
    }

    private function balanceFor(ProcurementInventoryLocation $location, string $stockKey): ?ProcurementInventoryStockBalance
    {
        return ProcurementInventoryStockBalance::query()
            ->where('location_id', (string) $location->id)
            ->where('stock_key', $stockKey)
            ->lockForUpdate()
            ->first();
    }

    private function recordInbound(
        ProcurementInventoryLocation $location,
        string $poLineId,
        string $description,
        ?string $uom,
        float $quantity,
        string $movementType,
        TenantUser $actor,
        ?string $grnId = null,
        ?string $grnLineId = null,
        ?string $counterpartyLocationId = null,
        ?string $transferBatchId = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): ProcurementInventoryStockMovement {
        $stockKey = self::stockKeyForPoLine($poLineId);
        $balance = $this->balanceFor($location, $stockKey);
        if ($balance === null) {
            $balance = ProcurementInventoryStockBalance::query()->create([
                'location_id' => (string) $location->id,
                'po_line_id' => $poLineId,
                'stock_key' => $stockKey,
                'description' => $description,
                'uom' => $uom,
                'quantity_on_hand' => 0,
            ]);
        }

        $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $quantity;
        $balance->description = $description;
        $balance->uom = $uom;
        $balance->save();

        return ProcurementInventoryStockMovement::query()->create([
            'movement_type' => $movementType,
            'transfer_batch_id' => $transferBatchId,
            'location_id' => (string) $location->id,
            'counterparty_location_id' => $counterpartyLocationId,
            'grn_id' => $grnId,
            'grn_line_id' => $grnLineId,
            'po_line_id' => $poLineId,
            'stock_key' => $stockKey,
            'description' => $description,
            'uom' => $uom,
            'quantity' => $quantity,
            'notes' => $notes,
            'metadata_json' => $metadata,
            'created_by_id' => (string) $actor->id,
        ]);
    }

    private function recordOutbound(
        ProcurementInventoryLocation $location,
        ?ProcurementInventoryLocation $counterparty,
        string $poLineId,
        string $description,
        ?string $uom,
        float $quantity,
        string $movementType,
        TenantUser $actor,
        ?string $transferBatchId = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): ProcurementInventoryStockMovement {
        $stockKey = self::stockKeyForPoLine($poLineId);
        $balance = $this->balanceFor($location, $stockKey);
        if ($balance === null || (float) $balance->quantity_on_hand + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [__('Insufficient stock at the selected location.')],
            ]);
        }

        $balance->quantity_on_hand = (float) $balance->quantity_on_hand - $quantity;
        $balance->save();

        return ProcurementInventoryStockMovement::query()->create([
            'movement_type' => $movementType,
            'transfer_batch_id' => $transferBatchId,
            'location_id' => (string) $location->id,
            'counterparty_location_id' => $counterparty?->id,
            'po_line_id' => $poLineId,
            'stock_key' => $stockKey,
            'description' => $description,
            'uom' => $uom,
            'quantity' => $quantity,
            'notes' => $notes,
            'metadata_json' => $metadata,
            'created_by_id' => (string) $actor->id,
        ]);
    }
}
