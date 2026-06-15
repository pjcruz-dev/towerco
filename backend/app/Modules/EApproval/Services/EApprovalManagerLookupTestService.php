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
        $requestorEmail = strtolower(trim($requestorEmail));
        if ($requestorEmail === '' || ! filter_var($requestorEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'message' => 'Enter a valid requestor email address.',
                'requestor_email' => $requestorEmail,
            ];
        }

        $managerEmail = $this->graph->getManagerEmailForUser($requestorEmail);
        if ($managerEmail === null) {
            return [
                'ok' => false,
                'message' => 'No manager found in Microsoft Entra for this user. Verify tenant Entra settings, Graph permissions, and org chart.',
                'requestor_email' => $requestorEmail,
                'manager_email' => null,
                'auto_provision_enabled' => $this->settings->provisionManagerUsers(),
            ];
        }

        $managerEmail = strtolower($managerEmail);
        $managerUser = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [$managerEmail])
            ->where('is_active', true)
            ->first();

        $autoProvision = $this->settings->provisionManagerUsers();

        return [
            'ok' => true,
            'message' => $managerUser !== null
                ? 'Manager resolved and mapped to an active TowerOS user.'
                : ($autoProvision
                    ? 'Manager found in Entra. A TowerOS approver account will be auto-provisioned on first submission.'
                    : 'Manager found in Entra but no matching active TowerOS user. Enable auto-provision in E-Approval settings or create the user.'),
            'requestor_email' => $requestorEmail,
            'manager_email' => $managerEmail,
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
