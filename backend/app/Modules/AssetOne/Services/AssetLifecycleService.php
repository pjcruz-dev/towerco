<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class AssetLifecycleService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function updateStatus(Asset $asset, array $input, TenantUser $actor): Asset
    {
        $status = (string) ($input['status'] ?? '');
        if (! AssetStatus::isValid($status)) {
            throw ValidationException::withMessages([
                'status' => [__('Asset status is invalid.')],
            ]);
        }

        $current = (string) $asset->status;
        if ($current !== $status && ! in_array($status, AssetStatus::allowedTransitions($current), true)) {
            throw ValidationException::withMessages([
                'status' => [__('This asset status transition is not allowed.')],
            ]);
        }

        $asset->status = $status;

        if (array_key_exists('location_type', $input)) {
            $asset->location_type = $input['location_type'];
        }
        if (array_key_exists('location_id', $input)) {
            $asset->location_id = $input['location_id'];
        }

        $asset->save();

        return $asset->refresh();
    }
}
