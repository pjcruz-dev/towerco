<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Models\AccountabilityItem;
use App\Modules\AssetManagement\Models\AccountabilitySignature;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetAssignment;
use App\Modules\AssetManagement\Models\AssetDisposal;
use App\Modules\AssetManagement\Models\AssetRepair;
use App\Modules\AssetManagement\Models\AssetRepairReplacement;
use App\Modules\AssetManagement\Models\HostnameHistory;
use App\Modules\AssetManagement\Models\Offboarding;
use App\Modules\AssetManagement\Models\OffboardingItem;
use App\Modules\AssetManagement\Support\AssetHostnamePolicy;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetWorkflowService
{
    public function __construct(
        private readonly AssetRegistryService $registry,
        private readonly AssetRecorder $recorder,
        private readonly TenantNotificationService $notifications,
    ) {}

    /**
     * @param  list<string>  $extraLabels
     */
    public function assign(Asset $asset, TenantUser $employee, TenantUser $actor, ?string $notes = null, array $extraLabels = []): Accountability
    {
        return DB::connection('tenant')->transaction(function () use ($asset, $employee, $actor, $notes, $extraLabels): Accountability {
            $status = AssetStatus::parse((string) $asset->status);
            if ($status === AssetStatus::UnderRepair) {
                throw ValidationException::withMessages([
                    'asset' => [__('Cannot transfer this asset. :tag is currently under repair. Complete or cancel the repair before transferring the asset.', ['tag' => $asset->asset_tag])],
                ]);
            }
            $status->assertCanTransitionTo(AssetStatus::Assigned, (string) $asset->asset_tag);
            $this->closeOpenAssignment($asset, $actor, 'Reassigned');
            AssetAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'assigned_at' => now(),
                'status' => 'ACTIVE',
                'assigned_by' => $actor->id,
                'notes' => $notes,
            ]);
            $asset->assigned_user_id = $employee->id;
            $asset->status = AssetStatus::Assigned->value;
            $asset->save();
            $items = [['asset_id' => (string) $asset->id, 'label' => $this->itemLabel($asset)]];
            foreach ($extraLabels as $label) {
                $items[] = ['asset_id' => null, 'label' => $label];
            }
            $draft = $this->draftAccountability($employee, $actor, $items);
            $this->recorder->event($asset, 'ASSIGNED', 'IT Storage', (string) $employee->name, $actor);

            return $draft->load('items');
        });
    }

    /**
     * @param  list<Asset>  $assets
     * @return array{valid: bool, errors: list<string>, count: int}
     */
    public function previewTransfer(array $assets, TenantUser $employee): array
    {
        $errors = [];
        if (! $employee->isActive()) {
            $errors[] = __('The destination employee is inactive.');
        }
        $serials = [];
        foreach ($assets as $asset) {
            if (isset($serials[$asset->serial_number])) {
                $errors[] = __('Duplicate serial :serial in this transfer.', ['serial' => $asset->serial_number]);
            }
            $serials[$asset->serial_number] = true;
            $status = AssetStatus::parse((string) $asset->status);
            if ($status !== AssetStatus::Assigned && $status !== AssetStatus::Available) {
                $errors[] = __('Asset :tag is :status and cannot be transferred.', [
                    'tag' => $asset->asset_tag,
                    'status' => $status->value,
                ]);
            }
            if ($status === AssetStatus::UnderRepair) {
                $errors[] = __('Cannot transfer :tag. It is under repair.', ['tag' => $asset->asset_tag]);
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'count' => count($assets)];
    }

    /**
     * @param  list<string>  $assetIds
     */
    public function transfer(array $assetIds, TenantUser $employee, TenantUser $actor, bool $confirmed): Accountability
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm' => [__('Preview the transfer, then confirm it.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($assetIds, $employee, $actor): Accountability {
            $assets = Asset::query()->whereIn('id', $assetIds)->lockForUpdate()->get()->all();
            if (count($assets) !== count(array_unique($assetIds))) {
                throw ValidationException::withMessages([
                    'assets' => [__('One or more assets could not be found.')],
                ]);
            }
            $preview = $this->previewTransfer($assets, $employee);
            if (! $preview['valid']) {
                throw ValidationException::withMessages(['assets' => $preview['errors']]);
            }
            $labels = [];
            foreach ($assets as $asset) {
                $from = $asset->assignee?->name ?? 'IT Storage';
                $this->closeOpenAssignment($asset, $actor, 'Transferred');
                AssetAssignment::query()->create([
                    'id' => (string) Str::uuid(),
                    'asset_id' => $asset->id,
                    'employee_id' => $employee->id,
                    'assigned_at' => now(),
                    'status' => 'ACTIVE',
                    'assigned_by' => $actor->id,
                ]);
                $asset->assigned_user_id = $employee->id;
                $asset->status = AssetStatus::Assigned->value;
                $asset->save();
                $this->recorder->event($asset, 'TRANSFER', (string) $from, (string) $employee->name, $actor);
                $labels[] = ['asset_id' => (string) $asset->id, 'label' => $this->itemLabel($asset)];
            }

            return $this->draftAccountability($employee, $actor, $labels)->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    public function returnAsset(Asset $asset, TenantUser $actor, string $nextStatus, array $inspection, ?string $expectedTag = null): Asset
    {
        return DB::connection('tenant')->transaction(function () use ($asset, $actor, $nextStatus, $inspection, $expectedTag): Asset {
            if ($expectedTag !== null && strcasecmp($expectedTag, (string) $asset->asset_tag) !== 0) {
                throw ValidationException::withMessages([
                    'asset' => [__('Wrong asset. Expected :expected. Scanned :scanned. Return cannot be completed.', [
                        'expected' => $expectedTag,
                        'scanned' => $asset->asset_tag,
                    ])],
                ]);
            }
            if (! isset($inspection['condition']) || trim((string) $inspection['condition']) === '') {
                throw ValidationException::withMessages([
                    'condition' => [__('Inspect the asset and record its condition before completing the return.')],
                ]);
            }
            $next = AssetStatus::parse($nextStatus);
            AssetStatus::parse((string) $asset->status)->assertCanTransitionTo($next, (string) $asset->asset_tag);
            $from = $asset->assignee?->name ?? 'Assigned';
            $this->closeOpenAssignment($asset, $actor, 'Returned');
            $asset->assigned_user_id = $next === AssetStatus::Assigned ? $asset->assigned_user_id : null;
            $asset->status = $next->value;
            $asset->save();
            $place = match ($next) {
                AssetStatus::UnderRepair => 'IT Repair',
                AssetStatus::Available => 'IT Storage',
                default => $next->value,
            };
            $this->recorder->event($asset, 'RETURN', (string) $from, $place, $actor, $inspection);

            return $asset->refresh();
        });
    }

    /**
     * @param  list<array{asset_id?: string|null, label: string}>  $items
     */
    public function updateAccountability(Accountability $record, array $items, ?string $notes, TenantUser $actor): Accountability
    {
        if ($record->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => [__('This accountability was already sent. Edit it only while it is a draft.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($record, $items, $notes): Accountability {
            $record->items()->delete();
            $order = 0;
            foreach ($items as $item) {
                AccountabilityItem::query()->create([
                    'id' => (string) Str::uuid(),
                    'accountability_id' => $record->id,
                    'asset_id' => $item['asset_id'] ?? null,
                    'label' => (string) $item['label'],
                    'sort_order' => $order++,
                ]);
            }
            if ($notes !== null) {
                $record->notes = $notes;
                $record->save();
            }

            return $record->refresh()->load('items');
        });
    }

    public function sendAccountability(Accountability $record, TenantUser $actor): Accountability
    {
        if ($record->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => [__('Only a draft accountability can be sent.')],
            ]);
        }
        if ($record->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => [__('Add at least one item before sending the accountability.')],
            ]);
        }
        $record->status = 'PENDING_SIGNATURE';
        $record->sent_by = $actor->id;
        $record->sent_at = now();
        $record->save();
        $this->notifications->notify(
            userId: (string) $record->employee_id,
            module: 'asset_management',
            type: 'accountability_sent',
            message: __('Sign the asset accountability :number.', ['number' => $record->number]),
            subjectType: 'accountability',
            subjectId: (string) $record->id,
            href: '/assets/accountability/'.$record->id,
            actor: $actor,
            category: 'action',
        );
        $assetId = $record->items()->whereNotNull('asset_id')->value('asset_id');
        $asset = is_string($assetId) ? Asset::query()->find($assetId) : null;
        if ($asset instanceof Asset) {
            $this->recorder->event($asset, 'ACCOUNTABILITY_SENT', null, null, $actor, [
                'accountability_id' => $record->id,
            ]);
        }

        return $record->refresh();
    }

    public function signAccountability(Accountability $record, TenantUser $actor, string $signature): Accountability
    {
        $isAssignee = (string) $actor->id === (string) $record->employee_id;
        $canRecord = $actor->can('assets:assign') || $actor->can('assets:manage');
        if (! $isAssignee && ! $canRecord) {
            throw ValidationException::withMessages([
                'employee' => [__('Only the assigned employee can sign this accountability.')],
            ]);
        }
        if ($record->status !== 'PENDING_SIGNATURE') {
            throw ValidationException::withMessages([
                'status' => [__('This accountability is not waiting for a signature.')],
            ]);
        }
        if (trim($signature) === '') {
            throw ValidationException::withMessages([
                'signature' => [__('A signature is required.')],
            ]);
        }
        AccountabilitySignature::query()->create([
            'id' => (string) Str::uuid(),
            'accountability_id' => $record->id,
            'signed_by' => $actor->id,
            'signature' => $signature,
            'signed_at' => now(),
        ]);
        $record->status = 'SIGNED';
        $record->signed_at = now();
        $record->save();
        $assetId = $record->items()->whereNotNull('asset_id')->value('asset_id');
        $asset = is_string($assetId) ? Asset::query()->find($assetId) : null;
        if ($asset instanceof Asset) {
            $employeeName = TenantUser::query()->whereKey($record->employee_id)->value('name');
            $this->recorder->event($asset, 'ACCOUNTABILITY_SIGNED', null, is_string($employeeName) ? $employeeName : null, $actor, [
                'accountability_id' => $record->id,
            ]);
        }

        return $record->refresh()->load('signatures');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openRepair(Asset $asset, TenantUser $actor, array $data): AssetRepair
    {
        $type = AssetType::parse((string) $asset->asset_type);
        $usable = array_key_exists('usable', $data) ? (bool) $data['usable'] : null;
        if ($type->isComputer() && $usable === null) {
            throw ValidationException::withMessages([
                'usable' => [__('Say whether the computer is currently usable before opening a repair.')],
            ]);
        }
        if ($usable === false && ! array_key_exists('replacement_required', $data)) {
            throw ValidationException::withMessages([
                'replacement_required' => [__('This computer is not usable. Say whether a replacement is required.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($asset, $actor, $data, $usable): AssetRepair {
            $repair = AssetRepair::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'status' => 'OPEN',
                'usable' => $usable,
                'replacement_required' => array_key_exists('replacement_required', $data) ? (bool) $data['replacement_required'] : null,
                'problem_type' => $data['problem_type'] ?? null,
                'severity' => $data['severity'] ?? null,
                'problem' => $data['problem'] ?? null,
                'notes' => $data['notes'] ?? null,
                'diagnosis' => $data['diagnosis'] ?? null,
                'vendor_email_subject' => $this->vendorSubject($asset),
                'vendor_email_body' => $this->vendorBody($asset, $data),
                'reported_by' => $actor->id,
                'reported_at' => now(),
            ]);
            if ($usable === false) {
                $asset->status = AssetStatus::UnderRepair->value;
                $asset->save();
            }
            $from = $asset->assignee?->name ?? 'Assigned';
            $this->recorder->event($asset, 'REPAIR', (string) $from, $usable === false ? 'IT Repair' : (string) $from, $actor, [
                'repair_id' => $repair->id,
                'usable' => $usable,
            ]);

            return $repair;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRepair(AssetRepair $repair, array $data, TenantUser $actor): AssetRepair
    {
        if (isset($data['vendor_email_subject'])) {
            $repair->vendor_email_subject = (string) $data['vendor_email_subject'];
        }
        if (isset($data['vendor_email_body'])) {
            $repair->vendor_email_body = (string) $data['vendor_email_body'];
        }
        if (isset($data['result'])) {
            $result = (string) $data['result'];
            $allowed = ['REPAIRED', 'PARTIALLY_REPAIRED', 'NOT_REPAIRABLE', 'VENDOR_REPLACEMENT', 'PENDING_VENDOR', 'CANCELLED'];
            if (! in_array($result, $allowed, true)) {
                throw ValidationException::withMessages(['result' => [__('Unknown repair result.')]]);
            }
            $repair->result = $result;
            $repair->status = $result === 'PENDING_VENDOR' ? 'OPEN' : 'CLOSED';
            $repair->closed_at = $repair->status === 'CLOSED' ? now() : null;
            $asset = Asset::query()->findOrFail($repair->asset_id);
            $next = match ($result) {
                'REPAIRED', 'PARTIALLY_REPAIRED' => $asset->assigned_user_id ? AssetStatus::Assigned : AssetStatus::Available,
                'NOT_REPAIRABLE' => AssetStatus::Retired,
                'VENDOR_REPLACEMENT' => AssetStatus::ReplacementPending,
                'CANCELLED' => $asset->assigned_user_id ? AssetStatus::Assigned : AssetStatus::Available,
                default => AssetStatus::parse((string) $asset->status),
            };
            if ($result !== 'PENDING_VENDOR') {
                AssetStatus::parse((string) $asset->status)->assertCanTransitionTo($next, (string) $asset->asset_tag);
                $asset->status = $next->value;
                $asset->save();
            }
            $this->recorder->event($asset, 'REPAIR', null, $next->value, $actor, ['result' => $result]);
        }
        $repair->save();

        return $repair->refresh();
    }

    public function issueReplacement(AssetRepair $repair, Asset $replacement, string $type, TenantUser $actor): AssetRepairReplacement
    {
        $type = strtoupper($type);
        if (! in_array($type, ['TEMPORARY', 'PERMANENT'], true)) {
            throw ValidationException::withMessages(['replacement_type' => [__('Replacement type must be temporary or permanent.')]]);
        }
        $original = Asset::query()->findOrFail($repair->asset_id);
        if ((string) $replacement->id === (string) $original->id) {
            throw ValidationException::withMessages(['replacement' => [__('Choose a different asset as the replacement.')]]);
        }
        if (AssetStatus::parse((string) $replacement->status) !== AssetStatus::Available) {
            throw ValidationException::withMessages([
                'replacement' => [__('Replacement :tag is not available.', ['tag' => $replacement->asset_tag])],
            ]);
        }
        $employeeId = $original->assigned_user_id;
        if ($employeeId === null) {
            throw ValidationException::withMessages(['employee' => [__('The original asset has no assignee to receive the replacement.')]]);
        }
        $employee = TenantUser::query()->findOrFail($employeeId);

        return DB::connection('tenant')->transaction(function () use ($repair, $replacement, $type, $actor, $original, $employee): AssetRepairReplacement {
            $link = AssetRepairReplacement::query()->create([
                'id' => (string) Str::uuid(),
                'repair_id' => $repair->id,
                'original_asset_id' => $original->id,
                'replacement_asset_id' => $replacement->id,
                'replacement_type' => $type,
                'status' => 'ISSUED',
                'issued_at' => now(),
            ]);
            $this->assign($replacement, $employee, $actor, 'Replacement for '.$original->asset_tag);
            $original->status = AssetStatus::UnderRepair->value;
            $original->save();
            $this->recorder->event($original, 'REPLACEMENT', (string) $original->asset_tag, (string) $replacement->asset_tag, $actor, [
                'type' => $type,
            ]);

            return $link;
        });
    }

    public function startOffboarding(TenantUser $employee, TenantUser $actor, ?string $lastDay): Offboarding
    {
        $open = Offboarding::query()->where('employee_id', $employee->id)->where('status', 'OPEN')->first();
        if ($open instanceof Offboarding) {
            return $open->load('items.asset');
        }
        $assignments = AssetAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'ACTIVE')
            ->get();
        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'employee' => [__('This user has no active asset assignments to return.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($employee, $actor, $lastDay, $assignments): Offboarding {
            $case = Offboarding::query()->create([
                'id' => (string) Str::uuid(),
                'number' => $this->nextNumber('OFF'),
                'employee_id' => $employee->id,
                'status' => 'OPEN',
                'last_working_day' => $lastDay,
                'started_by' => $actor->id,
            ]);
            foreach ($assignments as $assignment) {
                OffboardingItem::query()->create([
                    'id' => (string) Str::uuid(),
                    'offboarding_id' => $case->id,
                    'asset_id' => $assignment->asset_id,
                ]);
            }

            return $case->load('items.asset');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordOffboardingItem(OffboardingItem $item, string $scannedTag, array $data, TenantUser $actor): OffboardingItem
    {
        $item->load('asset');
        $expected = (string) $item->asset?->asset_tag;
        if (strcasecmp($expected, $scannedTag) !== 0) {
            throw ValidationException::withMessages([
                'asset' => [__('Wrong asset. Expected :expected. Scanned :scanned. Return cannot be completed.', [
                    'expected' => $expected,
                    'scanned' => $scannedTag,
                ])],
            ]);
        }
        $result = strtoupper((string) ($data['result'] ?? ''));
        $allowed = ['RETURNED', 'MISSING', 'DAMAGED', 'LOST', 'FOR_REPAIR', 'INCOMPLETE'];
        if (! in_array($result, $allowed, true)) {
            throw ValidationException::withMessages(['result' => [__('Choose a return result for this item.')]]);
        }
        if ($result === 'RETURNED' && (! isset($data['condition']) || trim((string) $data['condition']) === '')) {
            throw ValidationException::withMessages([
                'condition' => [__('Inspect :tag and record its condition.', ['tag' => $expected])],
            ]);
        }
        $item->fill([
            'result' => $result,
            'condition' => $data['condition'] ?? null,
            'physical_damage' => $data['physical_damage'] ?? null,
            'accessories' => $data['accessories'] ?? null,
            'battery_condition' => $data['battery_condition'] ?? null,
            'hardware_condition' => $data['hardware_condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'inspected_by' => $actor->id,
            'inspected_at' => now(),
        ]);
        $item->save();
        if (in_array($result, ['RETURNED', 'DAMAGED', 'FOR_REPAIR'], true) && $item->asset instanceof Asset) {
            $next = $result === 'FOR_REPAIR' || $result === 'DAMAGED' ? AssetStatus::UnderRepair : AssetStatus::Available;
            $current = AssetStatus::parse((string) $item->asset->status);
            if ($current->canTransitionTo($next)) {
                $this->returnAsset($item->asset, $actor, $next->value, [
                    'condition' => (string) ($data['condition'] ?? $result),
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        }
        if ($result === 'LOST' && $item->asset instanceof Asset) {
            $current = AssetStatus::parse((string) $item->asset->status);
            if ($current->canTransitionTo(AssetStatus::Lost)) {
                $item->asset->status = AssetStatus::Lost->value;
                $item->asset->save();
                $this->recorder->event($item->asset, 'RETURN', null, 'LOST', $actor);
            }
        }

        return $item->refresh();
    }

    public function completeOffboarding(Offboarding $case, TenantUser $actor): Offboarding
    {
        $unresolved = $case->items()->where(function ($query): void {
            $query->whereNull('result')->orWhereIn('result', ['MISSING', 'INCOMPLETE']);
        })->count();
        if ($unresolved > 0) {
            $case->status = 'INCOMPLETE';
            $case->save();
            throw ValidationException::withMessages([
                'offboarding' => [__('Offboarding stays incomplete. :count item(s) are still missing or unresolved.', ['count' => $unresolved])],
            ]);
        }
        $case->status = 'COMPLETE';
        $case->completed_at = now();
        $case->save();

        return $case->refresh()->load('items.asset');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispose(Asset $asset, TenantUser $actor, array $data): AssetDisposal
    {
        $disposition = strtoupper((string) ($data['disposition'] ?? ''));
        if (! in_array($disposition, ['BUYOUT', 'BIDDING', 'E_WASTE', 'DISPOSAL'], true)) {
            throw ValidationException::withMessages(['disposition' => [__('Choose buyout, bidding, e-waste, or disposal.')]]);
        }
        $next = $disposition === 'E_WASTE' ? AssetStatus::EWaste : AssetStatus::Disposed;
        $current = AssetStatus::parse((string) $asset->status);
        if ($current === AssetStatus::Retired && $next === AssetStatus::EWaste) {
            $current->assertCanTransitionTo(AssetStatus::EWaste, (string) $asset->asset_tag);
        } elseif ($current !== AssetStatus::Disposed) {
            if ($current->canTransitionTo(AssetStatus::Retired)) {
                $asset->status = AssetStatus::Retired->value;
                $asset->save();
                $current = AssetStatus::Retired;
            }
            $current->assertCanTransitionTo($next, (string) $asset->asset_tag);
        }
        $record = AssetDisposal::query()->create([
            'id' => (string) Str::uuid(),
            'asset_id' => $asset->id,
            'disposition' => $disposition,
            'disposition_date' => $data['disposition_date'] ?? now()->toDateString(),
            'approved_by' => $actor->id,
            'amount' => $data['amount'] ?? null,
            'buyer_vendor' => $data['buyer_vendor'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $asset->status = $next->value;
        $asset->assigned_user_id = null;
        $asset->save();
        $this->recorder->event($asset, $disposition, null, $next->value, $actor, ['disposal_id' => $record->id]);

        return $record;
    }

    public function recordHostname(Asset $asset, string $newHostname, string $reason, TenantUser $actor, bool $confirmedByOs): HostnameHistory
    {
        if (! $confirmedByOs) {
            throw ValidationException::withMessages([
                'hostname' => [__('The computer name is stored only after Windows reports that the rename succeeded.')],
            ]);
        }
        $hostname = AssetHostnamePolicy::assertLength($newHostname);
        $this->registry->assertHostnameAvailable($hostname, (string) $asset->id);
        $history = HostnameHistory::query()->create([
            'id' => (string) Str::uuid(),
            'asset_id' => $asset->id,
            'old_hostname' => $asset->hostname,
            'new_hostname' => $hostname,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
        $asset->old_hostname = $asset->hostname;
        $asset->hostname = $hostname;
        $asset->save();
        $this->recorder->event($asset, 'HOSTNAME_CHANGE', (string) $history->old_hostname, $hostname, $actor, ['reason' => $reason]);

        return $history;
    }

    /**
     * @param  list<array{asset_id: string|null, label: string}>  $items
     */
    private function draftAccountability(TenantUser $employee, TenantUser $actor, array $items): Accountability
    {
        $record = Accountability::query()->create([
            'id' => (string) Str::uuid(),
            'number' => $this->nextNumber('ACC'),
            'employee_id' => $employee->id,
            'status' => 'DRAFT',
            'created_by' => $actor->id,
        ]);
        $order = 0;
        foreach ($items as $item) {
            AccountabilityItem::query()->create([
                'id' => (string) Str::uuid(),
                'accountability_id' => $record->id,
                'asset_id' => $item['asset_id'],
                'label' => $item['label'],
                'sort_order' => $order++,
            ]);
        }

        return $record;
    }

    private function closeOpenAssignment(Asset $asset, TenantUser $actor, string $note): void
    {
        AssetAssignment::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'ACTIVE')
            ->update([
                'status' => 'CLOSED',
                'returned_at' => now(),
                'returned_by' => $actor->id,
                'notes' => $note,
            ]);
    }

    private function itemLabel(Asset $asset): string
    {
        $name = trim((string) $asset->manufacturer.' '.(string) $asset->product_name);

        return trim($asset->asset_tag.' '.($name !== '' ? $name : $asset->asset_type));
    }

    private function nextNumber(string $prefix): string
    {
        $column = $prefix === 'ACC' ? Accountability::query() : Offboarding::query();
        $latest = $column->orderByDesc('number')->value('number');
        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $match) === 1) {
            $seq = ((int) $match[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function vendorSubject(Asset $asset): string
    {
        return 'Repair Request – '.(string) $asset->manufacturer.' '.(string) $asset->product_name.' – SN '.$asset->serial_number;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function vendorBody(Asset $asset, array $data): string
    {
        $asset->loadMissing('assignee');
        $lines = [
            'Asset tag: '.$asset->asset_tag,
            'Serial number: '.$asset->serial_number,
            'Manufacturer: '.(string) $asset->manufacturer,
            'Model: '.(string) $asset->product_name,
            'Assigned employee: '.(string) ($asset->assignee?->name ?? ''),
            'Department: '.(string) ($asset->assignee?->department ?? ''),
            'Issue: '.(string) ($data['problem'] ?? ''),
            'Usability: '.(($data['usable'] ?? null) === false ? 'Not usable' : 'Usable'),
            'Initial diagnosis: '.(string) ($data['diagnosis'] ?? ''),
            'Warranty end: '.(string) ($asset->warranty_end ?? ''),
            'Requested action: Repair',
        ];

        return implode("\n", $lines);
    }
}
