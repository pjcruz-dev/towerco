<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalManagerChainResolution;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EApprovalManagerApproverResolver
{
    public function __construct(
        private readonly EntraGraphAppService $graph,
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * Resolve INFRA SUITE user id for the requestor's Entra manager.
     */
    public function resolveForSubmission(EApprovalSubmission $submission): ?string
    {
        $submission->loadMissing('requestor');
        $requestor = $submission->requestor;
        if ($requestor === null || trim((string) $requestor->email) === '') {
            return null;
        }

        return $this->resolveForEmail((string) $requestor->email);
    }

    public function resolveForEmail(string $requestorEmail): ?string
    {
        try {
            $lookup = $this->graph->lookupManagerForEmail($requestorEmail);
        } catch (\Throwable) {
            return null;
        }

        if (! $lookup->ok || $lookup->manager === null || $lookup->manager->email === '') {
            return null;
        }

        return $this->resolveUserIdForPerson($lookup->manager);
    }

    /**
     * Walk managers from the requestor upward.
     *
     * The nearest manager is first. The walk stops when a job title matches one of
     * $stopTitles (that person is included), when Entra has no further manager, or
     * at $maxLevels. A gap after the first person is a failure so a director is not
     * skipped. A miss on the first person is empty so the step can use its fallback.
     *
     * @param  list<string>  $stopTitles
     */
    public function resolveChain(string $requestorEmail, array $stopTitles, int $maxLevels): EApprovalManagerChainResolution
    {
        $requestorEmail = strtolower(trim($requestorEmail));
        $maxLevels = max(1, min(8, $maxLevels));
        $needles = [];
        foreach ($stopTitles as $title) {
            $needle = mb_strtolower(trim($title));
            if ($needle !== '') {
                $needles[$needle] = $needle;
            }
        }
        $needles = array_values($needles);

        if ($requestorEmail === '' || ! filter_var($requestorEmail, FILTER_VALIDATE_EMAIL)) {
            return EApprovalManagerChainResolution::failed('Enter a valid requestor email to resolve the reporting line.');
        }

        $current = $requestorEmail;
        $seenEmails = [$requestorEmail => true];
        $seenUsers = [];
        $people = [];

        for ($level = 0; $level < $maxLevels; $level++) {
            try {
                $lookup = $this->graph->lookupManagerForEmail($current);
            } catch (\Throwable) {
                return $people === []
                    ? EApprovalManagerChainResolution::empty()
                    : EApprovalManagerChainResolution::failed('Could not read the rest of the reporting line from Entra.', $people);
            }

            if (! $lookup->ok || $lookup->manager === null || trim($lookup->manager->email) === '') {
                if ($people !== [] && $lookup->code !== EntraManagerLookupResult::CODE_NO_MANAGER) {
                    return EApprovalManagerChainResolution::failed(
                        'Could not read the rest of the reporting line from Entra.',
                        $people,
                    );
                }

                break;
            }

            $manager = $lookup->manager;
            $email = strtolower(trim($manager->email));
            if ($email === '' || isset($seenEmails[$email])) {
                break;
            }
            $seenEmails[$email] = true;

            $userId = $this->resolveUserIdForPerson($manager);
            if ($userId === null || isset($seenUsers[$userId])) {
                if ($people === []) {
                    return EApprovalManagerChainResolution::empty();
                }

                $name = $manager->displayName !== '' ? $manager->displayName : $email;

                return EApprovalManagerChainResolution::failed(
                    "Reporting line includes {$name} ({$email}), who is not an active user in this workspace.",
                    $people,
                );
            }
            $seenUsers[$userId] = true;

            $matched = $this->jobTitleMatches($manager->jobTitle, $needles);
            $people[] = [
                'user_id' => $userId,
                'role_label' => $this->roleLabel($manager->jobTitle, $matched, $needles),
            ];

            if ($matched) {
                break;
            }

            $current = $email;
        }

        return new EApprovalManagerChainResolution($people, null);
    }

    public function resolveUserIdForPerson(EntraDirectoryPerson $manager): ?string
    {
        $existing = $this->findApproverUser($manager);
        if ($existing !== null) {
            return (string) $existing->id;
        }

        if (! $this->settings->provisionManagerUsers()) {
            return null;
        }

        return $this->provisionApprover($manager);
    }

    /**
     * @param  list<string>  $needles  already lowercased
     */
    private function jobTitleMatches(?string $jobTitle, array $needles): bool
    {
        $title = mb_strtolower(trim((string) $jobTitle));
        if ($title === '' || $needles === []) {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($title, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $needles
     */
    private function roleLabel(?string $jobTitle, bool $matched, array $needles): string
    {
        $title = trim((string) $jobTitle);
        if ($title !== '') {
            return mb_substr($title, 0, 120);
        }

        if ($matched && isset($needles[0])) {
            return mb_convert_case($needles[0], MB_CASE_TITLE, 'UTF-8');
        }

        return 'Manager';
    }

    private function findApproverUser(EntraDirectoryPerson $manager): ?TenantUser
    {
        if ($manager->entraId !== '' && Schema::connection('tenant')->hasColumn('users', 'entra_id')) {
            $byEntra = TenantUser::query()
                ->where('entra_id', $manager->entraId)
                ->where('is_active', true)
                ->first();
            if ($byEntra instanceof TenantUser) {
                return $byEntra;
            }
        }

        $byEmail = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [TenantUser::normalizeEmail($manager->email)])
            ->where('is_active', true)
            ->first();
        if ($byEmail instanceof TenantUser) {
            return $byEmail;
        }

        $local = strstr($manager->email, '@', true);
        if (! is_string($local) || $local === '') {
            return null;
        }

        $matches = TenantUser::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(email) LIKE ?', [strtolower($local).'@%'])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function provisionApprover(EntraDirectoryPerson $manager): string
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'name' => $manager->displayName !== '' ? $manager->displayName : $manager->email,
            'email' => TenantUser::normalizeEmail($manager->email),
            'password' => Str::random(32),
            'is_active' => true,
        ];
        if (Schema::connection('tenant')->hasColumn('users', 'entra_id')) {
            $payload['entra_id'] = $manager->entraId !== '' ? $manager->entraId : null;
            $payload['job_title'] = $manager->jobTitle;
        }

        /** @var TenantUser $user */
        $user = TenantUser::query()->create($payload);

        if ($user->getRoleNames()->isEmpty()) {
            try {
                $user->assignRole('e_approval_approver');
            } catch (\Throwable) {
                // Role may not exist on all tenants; user record is still usable for assignment.
            }
        }

        return (string) $user->id;
    }
}
