<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;

final class EApprovalManagerLookupTestService
{
    public function __construct(
        private readonly EntraGraphAppService $graph,
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * Preview Entra manager resolution without provisioning users.
     *
     * @return array<string, mixed>
     */
    public function preview(string $requestorEmail): array
    {
        $lookup = $this->graph->lookupManagerForEmail($requestorEmail);
        $autoProvision = $this->settings->provisionManagerUsers();

        if (! $lookup->ok || $lookup->manager === null) {
            return [
                'ok' => false,
                'code' => $lookup->code,
                'message' => $lookup->message,
                'requestor_email' => strtolower(trim($requestorEmail)),
                'manager_email' => null,
                'manager_name' => null,
                'auto_provision_enabled' => $autoProvision,
            ];
        }

        $managerEmail = $lookup->manager->email;
        $managerUser = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [$managerEmail])
            ->where('is_active', true)
            ->first();

        return [
            'ok' => true,
            'code' => $lookup->code,
            'message' => $managerUser !== null
                ? 'Manager resolved and mapped to an active TowerOS user ('.$lookup->manager->displayName.').'
                : ($autoProvision
                    ? 'Manager found in Entra ('.$lookup->manager->displayName.'). A TowerOS approver account will be auto-provisioned on first submission.'
                    : 'Manager found in Entra ('.$lookup->manager->displayName.') but no matching active TowerOS user. Enable auto-provision in E-Approval settings or create the user.'),
            'requestor_email' => strtolower(trim($requestorEmail)),
            'manager_email' => $managerEmail,
            'manager_name' => $lookup->manager->displayName,
            'manager_user' => $managerUser !== null ? [
                'id' => (string) $managerUser->id,
                'name' => (string) $managerUser->name,
                'email' => (string) $managerUser->email,
            ] : null,
            'auto_provision_enabled' => $autoProvision,
            'would_auto_provision' => $managerUser === null && $autoProvision,
        ];
    }
}
