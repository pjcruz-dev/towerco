<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSettingsUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalSettingsService $settings): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $data = $request->validate([
            'sla_reminder_minutes' => ['sometimes', 'integer', 'min:1'],
            'sla_escalation_minutes' => ['sometimes', 'integer', 'min:1'],
            'manual_follow_up_cooldown_minutes' => ['sometimes', 'integer', 'min:1'],
            'feature_delegation_ui' => ['sometimes', 'in:true,false'],
            'provision_manager_users' => ['sometimes', 'in:true,false'],
            'liquidation_requires_parent' => ['sometimes', 'in:true,false'],
            'liquidation_overspend_mode' => ['sometimes', 'in:block,warn'],
            'liquidation_max_overspend_percent' => ['sometimes', 'integer', 'min:0', 'max:25'],
            'po_overspend_mode' => ['sometimes', 'in:block,warn'],
            'po_max_overspend_percent' => ['sometimes', 'integer', 'min:0', 'max:25'],
        ]);

        $payload = [];
        foreach ($data as $key => $value) {
            $payload[$key] = (string) $value;
        }
        $settings->updateAdminSettings($payload);

        return $this->ok(['ok' => true]);
    }
}
