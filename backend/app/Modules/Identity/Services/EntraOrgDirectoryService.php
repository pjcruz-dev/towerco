<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraLicenseCatalog;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
        $skuMap = $this->skuMapForSync();
        if ($me !== null) {
            $this->applyProfile($user, $me, $skuMap);
        }

        $manager = $this->delegatedGraph->fetchManager($accessToken);
        $appToken = $this->appGraph->isConfigured() ? $this->appGraph->getAppAccessToken() : null;
        $this->applyManager($user, $manager, $skuMap, is_string($appToken) ? $appToken : null);

        foreach ($this->delegatedGraph->fetchDirectReports($accessToken) as $report) {
            $reportUser = TenantUser::findByEmail($report->email);
            if ($reportUser === null) {
                continue;
            }
            $this->applyProfile($reportUser, $report, $skuMap);
            $this->applyManager($reportUser, $me !== null
                ? $me
                : new EntraDirectoryPerson(
                    entraId: (string) ($user->entra_id ?? ''),
                    email: TenantUser::normalizeEmail((string) $user->email),
                    displayName: (string) $user->name,
                    jobTitle: $user->job_title,
                    department: $this->hasDepartmentColumn() ? $user->department : null,
                ), $skuMap, is_string($appToken) ? $appToken : null);
        }

        $user->entra_org_synced_at = now();
        $user->save();
        $this->propagateDepartmentsFromManagers();
    }

    /**
     * Match TowerOS users to Entra and copy manager / job title / department.
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     code: string,
     *     scanned: int,
     *     updated: int,
     *     managers_linked: int,
     *     skipped_unlicensed: int
     * }
     */
    public function syncDirectoryFromApp(int $limit = 200): array
    {
        if (! $this->hasOrgColumns()) {
            return $this->syncFailure(
                EntraManagerLookupResult::CODE_GRAPH_ERROR,
                'Organization fields are not migrated on this tenant database yet.',
            );
        }

        if (! $this->appGraph->isConfigured()) {
            return $this->syncFailure(
                EntraManagerLookupResult::CODE_NOT_CONFIGURED,
                'Microsoft Entra is not configured for this organization. Add the app client ID and secret under Administration → Sign-in & security.',
            );
        }

        $directory = $this->appGraph->directoryIdentifier();
        if ($directory === '' || $directory === 'common' || $directory === 'organizations' || $directory === 'consumers') {
            return $this->syncFailure(
                EntraManagerLookupResult::CODE_DIRECTORY_COMMON,
                'Set Directory ID to your Entra tenant GUID (not “common”). Client-credential Graph calls require the directory ID.',
            );
        }

        try {
            $token = $this->appGraph->getAppAccessToken();
        } catch (\Throwable $exception) {
            return $this->syncFailure(
                EntraManagerLookupResult::CODE_TOKEN_FAILED,
                'Could not reach Microsoft login to get an app token. '.$exception->getMessage(),
            );
        }
        if ($token === null) {
            return $this->syncFailure(
                EntraManagerLookupResult::CODE_TOKEN_FAILED,
                $this->appGraph->tokenFailureMessage()
                    ?? 'Could not get an app token from Microsoft. Check the client secret and Directory ID.',
            );
        }

        try {
            $skuMap = $this->appGraph->subscribedSkuMap($token);
        } catch (\Throwable) {
            $skuMap = [];
        }
        $users = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(max(1, min(500, $limit)))
            ->get();

        $updated = 0;
        $linked = 0;
        $skippedUnlicensed = 0;
        foreach ($users as $user) {
            try {
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
                            'skipped_unlicensed' => $skippedUnlicensed,
                        ];
                    }

                    continue;
                }

                $this->applyProfile($user, $found->person, $skuMap);
                $hadManager = $user->manager_id;
                $this->applyManager($user, $found->manager, $skuMap, $token);
                $user->entra_org_synced_at = now();
                $user->save();
                $updated++;
                if (! $found->person->isLicensed()) {
                    $skippedUnlicensed++;
                }
                if ($user->manager_id !== null && $user->manager_id !== $hadManager) {
                    $linked++;
                }
            } catch (\Throwable $exception) {
                Log::warning('Entra org sync skipped a user', [
                    'user_id' => (string) $user->id,
                    'email' => (string) $user->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $inherited = $this->propagateDepartmentsFromManagers();

        $message = $updated === 0
            ? 'No '.$this->workspaceLabel().' users matched Microsoft Entra mailboxes.'
            : "Updated {$updated} user".($updated === 1 ? '' : 's').' from Microsoft Entra.';
        if ($skippedUnlicensed > 0) {
            $message .= ' Hidden '.$skippedUnlicensed.' unlicensed account'.($skippedUnlicensed === 1 ? '' : 's').' from the organization chart.';
        }
        if ($inherited > 0) {
            $message .= ' Inherited department for '.$inherited.' report'.($inherited === 1 ? '' : 's').' from their manager.';
        }

        return [
            'ok' => true,
            'code' => EntraManagerLookupResult::CODE_OK,
            'message' => $message,
            'scanned' => $users->count(),
            'updated' => $updated,
            'managers_linked' => $linked,
            'skipped_unlicensed' => $skippedUnlicensed,
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

        $query = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name');
        $this->constrainLicensedOrgUsers($query);

        $users = $query->get([
            'id',
            'name',
            'email',
            'job_title',
            ...($this->hasDepartmentColumn() ? ['department'] : []),
            'manager_id',
            'entra_manager_email',
            'entra_manager_name',
            ...($this->hasManagerDepartmentColumn() ? ['entra_manager_department'] : []),
            'entra_org_synced_at',
            ...($this->hasLicenseColumns() ? ['entra_license_label', 'entra_license_names'] : []),
            ...($this->hasManagerLicenseColumns() ? ['entra_manager_licensed', 'entra_manager_license_label'] : []),
            ...($this->hasManagerParentColumn() ? ['entra_manager_parent_id'] : []),
        ]);

        $ids = $users->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $departmentsById = [];
        if ($this->hasDepartmentColumn()) {
            $departmentsById = $users
                ->mapWithKeys(static function (TenantUser $user): array {
                    $dept = is_string($user->department) ? trim($user->department) : '';

                    return [(string) $user->id => $dept !== '' ? $dept : null];
                })
                ->all();
        }

        $reportQuery = TenantUser::query()
            ->where('is_active', true)
            ->whereNotNull('manager_id');
        $this->constrainLicensedOrgUsers($reportQuery);
        $reportCounts = $reportQuery
            ->selectRaw('manager_id, COUNT(*) as aggregate')
            ->groupBy('manager_id')
            ->pluck('aggregate', 'manager_id');

        $latestSync = TenantUser::query()
            ->where('is_active', true)
            ->max('entra_org_synced_at');
        $syncedAt = null;
        if ($latestSync instanceof \DateTimeInterface) {
            $syncedAt = $latestSync->toIso8601String();
        } elseif (is_string($latestSync) && trim($latestSync) !== '') {
            $syncedAt = Carbon::parse($latestSync)->toIso8601String();
        }

        $people = $users->map(function (TenantUser $user) use ($ids, $reportCounts, $departmentsById): array {
            $managerId = $user->manager_id !== null ? (string) $user->manager_id : null;
            $managerInTenant = $managerId !== null && in_array($managerId, $ids, true);
            $showExternalManager = $this->externalManagerVisible($user, $managerInTenant);
            $parentId = $user->entra_manager_parent_id !== null ? (string) $user->entra_manager_parent_id : null;
            $managerDepartment = null;
            if ($managerInTenant && $managerId !== null) {
                $managerDepartment = $departmentsById[$managerId] ?? null;
            } elseif ($showExternalManager && $this->hasManagerDepartmentColumn()) {
                $managerDepartment = $this->clip($user->entra_manager_department, 180);
            }

            return [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'job_title' => $user->job_title,
                'department' => $this->hasDepartmentColumn() ? $user->department : null,
                'manager_id' => $managerInTenant ? $managerId : null,
                'manager_name' => $showExternalManager
                    ? ($user->entra_manager_name ?: $user->entra_manager_email)
                    : null,
                'manager_email' => $showExternalManager ? $user->entra_manager_email : null,
                'manager_department' => $managerDepartment,
                'manager_licensed' => $showExternalManager,
                'manager_license_label' => $showExternalManager ? $user->entra_manager_license_label : null,
                'manager_parent_id' => ($showExternalManager && $parentId !== null && in_array($parentId, $ids, true))
                    ? $parentId
                    : null,
                'direct_report_count' => (int) ($reportCounts[(string) $user->id] ?? 0),
                'license_label' => $this->hasLicenseColumns() ? $user->entra_license_label : null,
                'license_names' => $this->hasLicenseColumns() ? $this->licenseNames($user) : [],
            ];
        })->values()->all();

        return [
            'synced_at' => $syncedAt,
            'people' => $people,
        ];
    }

    /**
     * @param  array<string, string>  $skuMap
     */
    private function applyProfile(TenantUser $user, EntraDirectoryPerson $person, array $skuMap = []): void
    {
        if ($person->entraId !== '' && ! $this->entraIdTaken($person->entraId, (string) $user->id)) {
            $user->entra_id = $this->clip($person->entraId, 64);
        }
        if ($person->jobTitle !== null) {
            $user->job_title = $this->clip($person->jobTitle, 180);
        }
        if ($person->department !== null && $this->hasDepartmentColumn()) {
            $user->department = $this->clip($person->department, 180);
        }
        if (trim((string) $user->name) === '' || $user->name === $user->email) {
            $user->name = $this->clip($person->displayName, 255) ?? $person->email;
        }
        $this->applyLicense($user, $person, $skuMap);
    }

    /**
     * @param  array<string, string>  $skuMap
     */
    private function applyLicense(TenantUser $user, EntraDirectoryPerson $person, array $skuMap): void
    {
        if (! $this->hasLicenseColumns()) {
            return;
        }

        $summary = EntraLicenseCatalog::summarize($person->assignedSkuIds, $skuMap);
        $user->entra_licensed = $summary['licensed'];
        $user->entra_license_label = $summary['label'] !== null ? mb_substr($summary['label'], 0, 80) : null;
        $user->entra_license_names = array_values(array_map(
            static fn (string $name): string => mb_substr($name, 0, 180),
            $summary['names'],
        ));
    }

    /**
     * @param  array<string, string>  $skuMap
     */
    private function applyManager(TenantUser $user, ?EntraDirectoryPerson $manager, array $skuMap = [], ?string $token = null): void
    {
        if ($manager === null || $manager->email === '' || $manager->entraId === '') {
            $user->manager_id = null;
            $user->entra_manager_email = null;
            $user->entra_manager_name = null;
            $this->clearManagerDepartment($user);
            $this->clearManagerLicense($user);
            $this->clearManagerParent($user);

            return;
        }

        $user->entra_manager_email = $this->clip($manager->email, 255);
        $user->entra_manager_name = $this->clip($manager->displayName, 180);
        $this->storeManagerDepartment($user, $manager);
        $this->applyManagerLicense($user, $manager, $skuMap);

        $managerUser = $this->findManagerUser($manager);
        if ($managerUser === null || (string) $managerUser->id === (string) $user->id) {
            $user->manager_id = null;
            $this->linkEntraOnlyManagerParent($user, $manager, $token);
            $this->inheritDepartmentIfEmpty($user, $manager, null);

            return;
        }

        $this->clearManagerParent($user);

        if ($this->wouldCreateCycle((string) $user->id, (string) $managerUser->id)) {
            $user->manager_id = null;
            $this->inheritDepartmentIfEmpty($user, $manager, null);

            return;
        }

        $user->manager_id = $managerUser->id;
        $this->applyProfile($managerUser, $manager, $skuMap);
        if ($managerUser->isDirty()) {
            try {
                $managerUser->save();
            } catch (\Throwable $exception) {
                Log::warning('Entra org sync could not save manager profile', [
                    'user_id' => (string) $managerUser->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->inheritDepartmentIfEmpty($user, $manager, $managerUser);
    }

    /**
     * When Entra has no department on the report, copy the manager's department
     * so operators do not need every employee populated in Active Directory.
     */
    private function inheritDepartmentIfEmpty(
        TenantUser $user,
        ?EntraDirectoryPerson $manager,
        ?TenantUser $managerUser,
    ): void {
        if (! $this->hasDepartmentColumn()) {
            return;
        }
        if ($this->clip($user->department, 180) !== null) {
            return;
        }

        $fromGraph = $manager !== null ? $this->clip($manager->department, 180) : null;
        if ($fromGraph !== null) {
            $user->department = $fromGraph;

            return;
        }

        if ($managerUser !== null) {
            $fromManagerUser = $this->clip($managerUser->department, 180);
            if ($fromManagerUser !== null) {
                $user->department = $fromManagerUser;
            }
        }
    }

    /**
     * Fill empty departments from TowerOS manager chain (handles sync order + depth).
     *
     * @return int Number of users updated
     */
    private function propagateDepartmentsFromManagers(): int
    {
        if (! $this->hasDepartmentColumn()) {
            return 0;
        }

        $total = 0;
        for ($pass = 0; $pass < 8; $pass++) {
            $changed = 0;
            $reports = TenantUser::query()
                ->where('is_active', true)
                ->whereNotNull('manager_id')
                ->where(static function ($query): void {
                    $query->whereNull('department')->orWhere('department', '');
                })
                ->get(['id', 'manager_id', 'department']);

            foreach ($reports as $report) {
                $managerDept = TenantUser::query()
                    ->where('id', $report->manager_id)
                    ->value('department');
                $clipped = $this->clip(is_string($managerDept) ? $managerDept : null, 180);
                if ($clipped === null) {
                    continue;
                }
                $report->department = $clipped;
                $report->save();
                $changed++;
            }

            // Entra-only managers: use stored Graph manager department.
            if ($this->hasManagerDepartmentColumn()) {
                $externalReports = TenantUser::query()
                    ->where('is_active', true)
                    ->whereNull('manager_id')
                    ->whereNotNull('entra_manager_department')
                    ->where('entra_manager_department', '!=', '')
                    ->where(static function ($query): void {
                        $query->whereNull('department')->orWhere('department', '');
                    })
                    ->get(['id', 'department', 'entra_manager_department']);

                foreach ($externalReports as $report) {
                    $clipped = $this->clip($report->entra_manager_department, 180);
                    if ($clipped === null) {
                        continue;
                    }
                    $report->department = $clipped;
                    $report->save();
                    $changed++;
                }
            }

            $total += $changed;
            if ($changed === 0) {
                break;
            }
        }

        return $total;
    }

    private function storeManagerDepartment(TenantUser $user, EntraDirectoryPerson $manager): void
    {
        if (! $this->hasManagerDepartmentColumn()) {
            return;
        }

        $user->entra_manager_department = $this->clip($manager->department, 180);
    }

    private function clearManagerDepartment(TenantUser $user): void
    {
        if (! $this->hasManagerDepartmentColumn()) {
            return;
        }

        $user->entra_manager_department = null;
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

    private function hasDepartmentColumn(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'department');
    }

    private function hasManagerDepartmentColumn(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'entra_manager_department');
    }

    private function hasLicenseColumns(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'entra_licensed');
    }

    private function hasManagerLicenseColumns(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'entra_manager_licensed');
    }

    private function hasManagerParentColumn(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'entra_manager_parent_id');
    }

    private function linkEntraOnlyManagerParent(TenantUser $user, EntraDirectoryPerson $manager, ?string $token): void
    {
        $this->clearManagerParent($user);
        if (! $this->hasManagerParentColumn() || ! is_string($token) || $token === '' || $manager->entraId === '') {
            return;
        }

        try {
            $skip = $this->appGraph->fetchManagerPerson($token, $manager->entraId);
        } catch (\Throwable) {
            return;
        }
        if ($skip === null) {
            return;
        }

        $parent = $this->findManagerUser($skip);
        if ($parent === null || (string) $parent->id === (string) $user->id) {
            return;
        }
        if ($this->wouldCreateCycle((string) $user->id, (string) $parent->id)) {
            return;
        }

        $user->entra_manager_parent_id = $parent->id;
    }

    private function clearManagerParent(TenantUser $user): void
    {
        if (! $this->hasManagerParentColumn()) {
            return;
        }

        $user->entra_manager_parent_id = null;
    }

    private function externalManagerVisible(TenantUser $user, bool $managerInTenant): bool
    {
        if ($managerInTenant) {
            return false;
        }

        if (! $this->hasManagerLicenseColumns()) {
            return trim((string) ($user->entra_manager_name ?: $user->entra_manager_email)) !== '';
        }

        return (bool) $user->entra_manager_licensed;
    }

    /**
     * @param  array<string, string>  $skuMap
     */
    private function applyManagerLicense(TenantUser $user, EntraDirectoryPerson $manager, array $skuMap): void
    {
        if (! $this->hasManagerLicenseColumns()) {
            return;
        }

        $summary = EntraLicenseCatalog::summarize($manager->assignedSkuIds, $skuMap);
        $user->entra_manager_licensed = $summary['licensed'];
        $user->entra_manager_license_label = $summary['label'] !== null ? mb_substr($summary['label'], 0, 80) : null;
    }

    private function clearManagerLicense(TenantUser $user): void
    {
        if (! $this->hasManagerLicenseColumns()) {
            return;
        }

        $user->entra_manager_licensed = null;
        $user->entra_manager_license_label = null;
    }

    /**
     * Organization chart: licensed Microsoft 365 users only.
     * After any org sync, never-synced local/test accounts are hidden too.
     */
    private function constrainLicensedOrgUsers(\Illuminate\Database\Eloquent\Builder $query): void
    {
        if (! $this->hasLicenseColumns()) {
            return;
        }

        $hasCompletedOrgSync = TenantUser::query()
            ->whereNotNull('entra_org_synced_at')
            ->exists();
        if ($hasCompletedOrgSync) {
            $query->where('entra_licensed', true);

            return;
        }

        $query->where(static function ($q): void {
            $q->where('entra_licensed', true)
                ->orWhere(static function ($inner): void {
                    $inner->whereNull('entra_licensed')->whereNull('entra_org_synced_at');
                });
        });
    }

    private function entraIdTaken(string $entraId, string $exceptUserId): bool
    {
        return TenantUser::query()
            ->where('entra_id', $entraId)
            ->where('id', '!=', $exceptUserId)
            ->exists();
    }

    private function clip(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $max);
    }

    /**
     * @return list<string>
     */
    private function licenseNames(TenantUser $user): array
    {
        $names = $user->entra_license_names;
        if (! is_array($names)) {
            return [];
        }

        return array_values(array_filter($names, static fn (mixed $name): bool => is_string($name) && $name !== ''));
    }

    /**
     * @return array<string, string>
     */
    private function skuMapForSync(): array
    {
        if (! $this->appGraph->isConfigured()) {
            return [];
        }
        try {
            $token = $this->appGraph->getAppAccessToken();
            if (! is_string($token) || $token === '') {
                return [];
            }

            return $this->appGraph->subscribedSkuMap($token);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     code: string,
     *     scanned: int,
     *     updated: int,
     *     managers_linked: int,
     *     skipped_unlicensed: int
     * }
     */
    private function syncFailure(string $code, string $message): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'scanned' => 0,
            'updated' => 0,
            'managers_linked' => 0,
            'skipped_unlicensed' => 0,
        ];
    }
}
