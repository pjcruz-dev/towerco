<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantAuthUserPayloadBuilder;
use App\Modules\Tenancy\Support\FrontendDevUrl;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Phase 3: central one-time ticket for cross-environment workspace switch.
 */
final class TenantEnvironmentHandoffService
{
    public function __construct(
        private readonly TenantLinkedEnvironmentsService $linkedEnvironments,
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly MfaService $mfaService,
        private readonly AuthAuditService $auditService,
        private readonly TenantAuthUserPayloadBuilder $authUserPayload,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('toweros.environment_switch.enabled', true);
    }

    /**
     * @return array{redeem_url: string, expires_at: string, target_environment: string, target_hostname: string}
     */
    public function mint(
        Tenant $sourceTenant,
        TenantUser $actor,
        string $targetEnvironment,
        ?string $sourceSessionId = null,
    ): array {
        if (! $this->enabled()) {
            throw new HttpException(503, __('Environment switch is disabled.'));
        }

        if (! $actor->can('workspace:environments:switch')) {
            throw new HttpException(403, __('You do not have permission to switch environments.'));
        }

        if (! $actor->isActive()) {
            throw ValidationException::withMessages([
                'environment' => [__('Your account is inactive.')],
            ]);
        }

        $email = TenantUser::normalizeEmail((string) $actor->email);
        if ($email === '') {
            throw ValidationException::withMessages([
                'environment' => [__('Your account email is required to switch environments.')],
            ]);
        }

        $targetEnvironment = strtolower(trim($targetEnvironment));
        $listing = $this->linkedEnvironments->listForTenant($sourceTenant);
        $target = collect($listing['environments'])
            ->first(static fn (array $env): bool => $env['environment'] === $targetEnvironment && ! $env['is_current']);

        if (! is_array($target)) {
            throw ValidationException::withMessages([
                'environment' => [__('That environment is not available for this organization.')],
            ]);
        }

        /** @var Tenant|null $targetTenant */
        $targetTenant = Tenant::query()
            ->where('environment', $targetEnvironment)
            ->where(function ($query) use ($sourceTenant): void {
                $orgRoot = $this->resolveOrgRoot($sourceTenant);
                $query->where('id', $orgRoot->id)
                    ->orWhere('parent_tenant_id', $orgRoot->id);
            })
            ->first();

        if ($targetTenant === null || (string) $targetTenant->id === (string) $sourceTenant->id) {
            throw ValidationException::withMessages([
                'environment' => [__('That environment is not available for this organization.')],
            ]);
        }

        $ttl = max(30, (int) config('toweros.environment_switch.ticket_ttl_seconds', 90));
        $plainTicket = Str::random(64);
        $expiresAt = now()->addSeconds($ttl);
        $ticketId = (string) Str::uuid();

        $this->tickets()->insert([
            'id' => $ticketId,
            'token_hash' => hash('sha256', $plainTicket),
            'source_tenant_id' => (string) $sourceTenant->id,
            'target_tenant_id' => (string) $targetTenant->id,
            'source_user_id' => (string) $actor->getKey(),
            'actor_email' => $email,
            'source_environment' => strtolower((string) ($sourceTenant->environment ?? '')),
            'target_environment' => $targetEnvironment,
            'source_session_id' => $sourceSessionId,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'consumed_ip' => null,
            'consumed_user_agent' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditService->log('auth.environment_switch.minted', (string) $actor->getKey(), $sourceSessionId, [
            'target_environment' => $targetEnvironment,
            'target_hostname' => $target['hostname'],
            'ticket_id' => $ticketId,
        ]);

        return [
            'redeem_url' => FrontendDevUrl::tenantEnvironmentHandoffUrl(
                $target['hostname'],
                $plainTicket,
                $targetEnvironment,
            ),
            'expires_at' => $expiresAt->toIso8601String(),
            'target_environment' => $targetEnvironment,
            'target_hostname' => $target['hostname'],
        ];
    }

    /**
     * Redeem on the target tenant (current tenancy must be the target).
     *
     * @return array<string, mixed> Login-shaped session payload
     */
    public function redeem(Tenant $targetTenant, string $plainTicket, ?string $ip, ?string $userAgent): array
    {
        if (! $this->enabled()) {
            throw new HttpException(503, __('Environment switch is disabled.'));
        }

        $hash = hash('sha256', $plainTicket);

        $row = $this->tickets()
            ->where('token_hash', $hash)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'ticket' => [__('Invalid or expired switch ticket.')],
            ]);
        }

        if ($row->consumed_at !== null) {
            throw ValidationException::withMessages([
                'ticket' => [__('This switch ticket was already used.')],
            ]);
        }

        if (now()->greaterThan($row->expires_at)) {
            throw ValidationException::withMessages([
                'ticket' => [__('This switch ticket has expired.')],
            ]);
        }

        if ((string) $row->target_tenant_id !== (string) $targetTenant->id) {
            throw ValidationException::withMessages([
                'ticket' => [__('This switch ticket is for a different environment.')],
            ]);
        }

        /** @var Tenant|null $sourceTenant */
        $sourceTenant = Tenant::query()->find($row->source_tenant_id);
        if ($sourceTenant === null || ! $this->sameOrganization($sourceTenant, $targetTenant)) {
            throw ValidationException::withMessages([
                'ticket' => [__('This switch ticket is no longer valid for the organization.')],
            ]);
        }

        $email = TenantUser::normalizeEmail((string) $row->actor_email);
        $user = $this->resolveTargetUser($email, $targetTenant);

        if ($user === null) {
            $this->auditService->log('auth.environment_switch.denied', null, null, [
                'reason' => 'user_not_found',
                'actor_email' => $email,
                'source_tenant_id' => $row->source_tenant_id,
                'candidates' => $this->candidateEmailsForTarget($email, $targetTenant),
            ], 'medium');

            throw ValidationException::withMessages([
                'ticket' => [__(
                    'No matching active user was found in this environment for :email. Add that user on the target environment (or enable Microsoft SSO there), then try Switch again.',
                    ['email' => $email],
                )],
                'login_email' => [$email],
            ]);
        }

        // Consume only after identity is resolved so retries remain possible when the user is missing.
        $this->tickets()
            ->where('id', $row->id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'consumed_ip' => $ip !== null ? substr($ip, 0, 45) : null,
                'consumed_user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
                'updated_at' => now(),
            ]);

        $sessionId = $this->sessionService->start((string) $user->getKey(), 'environment_switch');
        $accessToken = $user->createToken(
            name: 'access',
            abilities: ['*', 'session:'.$sessionId],
            expiresAt: now()->addMinutes((int) env('TENANT_ACCESS_TOKEN_TTL_MINUTES', 60)),
        )->plainTextToken;
        $refresh = $this->refreshTokenService->issue((string) $user->getKey(), $sessionId);

        $mfaState = $this->mfaService->resolveLoginMfaState($user, $sessionId);
        if ($mfaState['mark_verified']) {
            $this->sessionService->markMfaVerified($sessionId);
        }

        $this->auditService->log('auth.environment_switch.redeemed', (string) $user->getKey(), $sessionId, [
            'source_tenant_id' => $row->source_tenant_id,
            'source_environment' => $row->source_environment,
            'mfa_required' => $mfaState['mfa_required'],
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refresh['token'],
            'session_id' => $sessionId,
            'mfa_required' => $mfaState['mfa_required'],
            'mfa_enrollment_required' => $mfaState['mfa_enrollment_required'],
            'mfa_challenge' => $mfaState['mfa_challenge'],
            'user' => $this->authUserPayload->build($user),
        ];
    }

    /**
     * Wrap redeem in a central DB transaction so ticket lock + consume are atomic.
     *
     * @return array<string, mixed>
     */
    public function redeemAtomically(Tenant $targetTenant, string $plainTicket, ?string $ip, ?string $userAgent): array
    {
        return $this->central()->transaction(function () use ($targetTenant, $plainTicket, $ip, $userAgent): array {
            return $this->redeem($targetTenant, $plainTicket, $ip, $userAgent);
        });
    }

    /**
     * Resolve the actor on the target tenant.
     *
     * Bootstrap admins are created as admin@{tenant-hostname}, so staging
     * `admin@staging.myapp.localhost` must map to production `admin@app.myapp.localhost`.
     */
    private function resolveTargetUser(string $actorEmail, Tenant $targetTenant): ?TenantUser
    {
        foreach ($this->candidateEmailsForTarget($actorEmail, $targetTenant) as $candidate) {
            $user = TenantUser::findByEmail($candidate);
            if ($user !== null && $user->isActive()) {
                return $user;
            }
        }

        return $this->findUniqueActiveUserByLocalPart($actorEmail);
    }

    /**
     * @return list<string>
     */
    public function candidateEmailsForTarget(string $actorEmail, Tenant $targetTenant): array
    {
        $email = TenantUser::normalizeEmail($actorEmail);
        if ($email === '') {
            return [];
        }

        $candidates = [$email];
        $at = strrpos($email, '@');
        if ($at === false) {
            return $candidates;
        }

        $local = substr($email, 0, $at);
        if ($local === '') {
            return $candidates;
        }

        $hostname = $this->primaryHostname($targetTenant);
        if ($hostname !== null && $hostname !== '') {
            $candidates[] = TenantUser::normalizeEmail($local.'@'.$hostname);
        }

        return array_values(array_unique($candidates));
    }

    private function findUniqueActiveUserByLocalPart(string $actorEmail): ?TenantUser
    {
        $email = TenantUser::normalizeEmail($actorEmail);
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $local = strtolower(substr($email, 0, $at));
        if ($local === '') {
            return null;
        }

        /** @var Collection<int, TenantUser> $matches */
        $matches = TenantUser::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(email) LIKE ?', [$local.'@%'])
            ->limit(5)
            ->get()
            ->filter(static function (TenantUser $user) use ($local): bool {
                if (! $user->isActive()) {
                    return false;
                }

                $normalized = TenantUser::normalizeEmail((string) $user->email);
                $userAt = strrpos($normalized, '@');
                if ($userAt === false) {
                    return false;
                }

                return substr($normalized, 0, $userAt) === $local;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function primaryHostname(Tenant $tenant): ?string
    {
        $domain = $tenant->domains->first()?->domain
            ?? $tenant->domains()->first()?->domain
            ?? null;

        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        return strtolower(trim($domain));
    }

    private function sameOrganization(Tenant $left, Tenant $right): bool
    {
        return (string) $this->resolveOrgRoot($left)->id === (string) $this->resolveOrgRoot($right)->id;
    }

    private function resolveOrgRoot(Tenant $tenant): Tenant
    {
        $current = $tenant;

        while ($current->parent_tenant_id !== null) {
            /** @var Tenant|null $parent */
            $parent = Tenant::query()->find($current->parent_tenant_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }

        return $current;
    }

    private function tickets(): Builder
    {
        return $this->central()->table('environment_switch_tickets');
    }

    private function central(): Connection
    {
        $name = (string) config('tenancy.database.central_connection', 'central');

        return DB::connection($name);
    }
}
