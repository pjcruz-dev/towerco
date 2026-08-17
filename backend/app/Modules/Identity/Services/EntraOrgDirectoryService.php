<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class EntraOrgDirectoryService
{
    public function __construct(
        private readonly AzureGraphService $delegatedGraph,
        private readonly EntraGraphAppService $appGraph,
    ) {}

    public function syncFromDelegatedToken(TenantUser $user, string $accessToken): void
    {
        if (! $this->hasOrgColumns()) {
            return;
        }

        $me = $this->delegatedGraph->fetchMe($accessToken);
        if ($me !== null) {
            $this->applyProfile($user, $me);
        }

        $manager = $this->delegatedGraph->fetchManager($accessToken);
        $this->applyManager($user, $manager);

        foreach ($this->delegatedGraph->fetchDirectReports($accessToken) as $report) {
            $reportUser = TenantUser::findByEmail($report->email);
            if ($reportUser === null) {
                continue;
            }
            $this->applyProfile($reportUser, $report);
            $this->applyManager($reportUser, $me !== null
                ? $me
                : new EntraDirectoryPerson(
                    entraId: (string) ($user->entra_id ?? ''),
                    email: TenantUser::normalizeEmail((string) $user->email),
                    displayName: (string) $user->name,
                    jobTitle: $user->job_title,
                ));
        }

        $user->entra_org_synced_at = now();
        $user->save();
    }

    /**
     * Match TowerOS users to Entra and copy manager / job title.
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     code: string,
     *     scanned: int,
     *     updated: int,
     *     managers_linked: int
     * }
     */
    public function syncDirectoryFromApp(int $limit = 200): array
    {
        if (! $this->hasOrgColumns()) {
            return [
                'ok' => false,
                'code' => EntraManagerLookupResult::CODE_GRAPH_ERROR,
                'message' => 'Organization fields are not migrated on this tenant database yet.',
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
            ];
        }

        if (! $this->appGraph->isConfigured()) {
            return [
                'ok' => false,
                'code' => EntraManagerLookupResult::CODE_NOT_CONFIGURED,
                'message' => 'Microsoft Entra is not configured for this organization. Add the app client ID and secret under Administration → Sign-in & security.',
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
            ];
        }

        $directory = $this->appGraph->directoryIdentifier();
        if ($directory === '' || $directory === 'common' || $directory === 'organizations' || $directory === 'consumers') {
            return [
                'ok' => false,
                'code' => EntraManagerLookupResult::CODE_DIRECTORY_COMMON,
                'message' => 'Set Directory ID to your Entra tenant GUID (not “common”). Client-credential Graph calls require the directory ID.',
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
            ];
        }

        $token = $this->appGraph->getAppAccessToken(forceRefresh: true);
        if ($token === null) {
            return [
                'ok' => false,
                'code' => EntraManagerLookupResult::CODE_TOKEN_FAILED,
                'message' => 'Could not get an app token from Microsoft. Check the client secret and Directory ID.',
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
            ];
        }

        $users = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(max(1, min(500, $limit)))
            ->get();

        $updated = 0;
        $linked = 0;
        foreach ($users as $user) {
            $found = $this->appGraph->findUserWithManager($token, TenantUser::normalizeEmail((string) $user->email));
            if ($found instanceof EntraManagerLookupResult) {
                if ($found->code === EntraManagerLookupResult::CODE_FORBIDDEN) {
                    return [
                        'ok' => false,
                        'code' => $found->code,
                        'message' => $found->message,
                        'scanned' => $users->count(),
                        'updated' => $updated,
                        'managers_linked' => $linked,
                    ];
                }

                continue;
            }

            $this->applyProfile($user, $found->person);
            $hadManager = $user->manager_id;
            $this->applyManager($user, $found->manager);
            $user->entra_org_synced_at = now();
            $user->save();
            $updated++;
            if ($user->manager_id !== null && $user->manager_id !== $hadManager) {
                $linked++;
            }
        }

        return [
            'ok' => true,
            'code' => EntraManagerLookupResult::CODE_OK,
            'message' => $updated === 0
                ? 'No '.$this->workspaceLabel().' users matched Microsoft Entra mailboxes.'
                : "Updated {$updated} user".($updated === 1 ? '' : 's').' from Microsoft Entra.',
            'scanned' => $users->count(),
            'updated' => $updated,
            'managers_linked' => $linked,
        ];
    }

    /**
     * @return array{synced_at: string|null, people: list<array<string, mixed>>}
     */
    public function orgChart(): array
    {
        if (! $this->hasOrgColumns()) {
            return ['synced_at' => null, 'people' => []];
        }

        $users = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'job_title',
                'manager_id',
                'entra_manager_email',
                'entra_manager_name',
                'entra_org_synced_at',
            ]);

        $ids = $users->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $reportCounts = TenantUser::query()
            ->where('is_active', true)
            ->whereNotNull('manager_id')
            ->selectRaw('manager_id, COUNT(*) as aggregate')
            ->groupBy('manager_id')
            ->pluck('aggregate', 'manager_id');

        $latestSync = $users->max('entra_org_synced_at');
        $syncedAt = null;
        if ($latestSync instanceof \DateTimeInterface) {
            $syncedAt = $latestSync->toIso8601String();
        } elseif (is_string($latestSync) && trim($latestSync) !== '') {
            $syncedAt = Carbon::parse($latestSync)->toIso8601String();
        }

        $people = $users->map(function (TenantUser $user) use ($ids, $reportCounts): array {
            $managerId = $user->manager_id !== null ? (string) $user->manager_id : null;
            $managerInTenant = $managerId !== null && in_array($managerId, $ids, true);

            return [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'job_title' => $user->job_title,
                'manager_id' => $managerInTenant ? $managerId : null,
                'manager_name' => $managerInTenant
                    ? null
                    : ($user->entra_manager_name ?: $user->entra_manager_email),
                'manager_email' => $managerInTenant ? null : $user->entra_manager_email,
                'direct_report_count' => (int) ($reportCounts[(string) $user->id] ?? 0),
            ];
        })->values()->all();

        return [
            'synced_at' => $syncedAt,
            'people' => $people,
        ];
    }

    private function applyProfile(TenantUser $user, EntraDirectoryPerson $person): void
    {
        $user->entra_id = $person->entraId;
        if ($person->jobTitle !== null) {
            $user->job_title = $person->jobTitle;
        }
        if (trim((string) $user->name) === '' || $user->name === $user->email) {
            $user->name = $person->displayName;
        }
    }

    private function applyManager(TenantUser $user, ?EntraDirectoryPerson $manager): void
    {
        if ($manager === null || $manager->email === '' || $manager->entraId === '') {
            $user->manager_id = null;
            $user->entra_manager_email = null;
            $user->entra_manager_name = null;

            return;
        }

        $user->entra_manager_email = $manager->email;
        $user->entra_manager_name = $manager->displayName;

        $managerUser = $this->findManagerUser($manager);
        if ($managerUser === null || (string) $managerUser->id === (string) $user->id) {
            $user->manager_id = null;

            return;
        }

        if ($this->wouldCreateCycle((string) $user->id, (string) $managerUser->id)) {
            $user->manager_id = null;

            return;
        }

        $user->manager_id = $managerUser->id;
        $this->applyProfile($managerUser, $manager);
        if ($managerUser->isDirty()) {
            $managerUser->save();
        }
    }

    private function findManagerUser(EntraDirectoryPerson $manager): ?TenantUser
    {
        if ($manager->entraId !== '') {
            $byEntra = TenantUser::query()->where('entra_id', $manager->entraId)->first();
            if ($byEntra instanceof TenantUser) {
                return $byEntra;
            }
        }

        $byEmail = TenantUser::findByEmail($manager->email);
        if ($byEmail instanceof TenantUser) {
            return $byEmail;
        }

        $local = strstr($manager->email, '@', true);
        if (! is_string($local) || $local === '') {
            return null;
        }

        $matches = TenantUser::query()
            ->whereRaw('LOWER(email) LIKE ?', [strtolower($local).'@%'])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function wouldCreateCycle(string $userId, string $managerId): bool
    {
        $cursor = $managerId;
        for ($i = 0; $i < 32; $i++) {
            if ($cursor === $userId) {
                return true;
            }
            $next = TenantUser::query()->where('id', $cursor)->value('manager_id');
            if (! is_string($next) || $next === '') {
                return false;
            }
            $cursor = $next;
        }

        return true;
    }

    private function workspaceLabel(): string
    {
        $slug = trim((string) (tenant('slug') ?? ''));

        return $slug !== '' ? strtoupper($slug) : 'workspace';
    }

    private function hasOrgColumns(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'manager_id');
    }
}
