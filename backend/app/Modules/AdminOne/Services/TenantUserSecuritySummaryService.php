<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Services\MfaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TenantUserSecuritySummaryService
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    /**
     * @param  list<string>  $userIds
     * @return array<string, array{
     *   last_active_at: string|null,
     *   auth_methods: list<string>,
     *   mfa_enrolled: bool,
     *   mfa_required: bool
     * }>
     */
    public function summarizeForUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $lastActiveRows = DB::connection('tenant')->table('auth_sessions')
            ->select('user_id', DB::raw('MAX(last_seen_at) as last_active_at'))
            ->whereIn('user_id', $userIds)
            ->where('state', 'active')
            ->whereNull('revoked_at')
            ->whereNotNull('last_seen_at')
            ->groupBy('user_id')
            ->get();

        $lastActiveByUser = [];
        foreach ($lastActiveRows as $row) {
            $timestamp = $row->last_active_at ?? null;
            $lastActiveByUser[(string) $row->user_id] = $timestamp !== null
                ? Carbon::parse((string) $timestamp)->toIso8601String()
                : null;
        }

        $authMethodRows = DB::connection('tenant')->table('auth_sessions')
            ->select('user_id', 'auth_method')
            ->whereIn('user_id', $userIds)
            ->where('state', 'active')
            ->whereNull('revoked_at')
            ->whereNotNull('auth_method')
            ->groupBy('user_id', 'auth_method')
            ->orderBy('auth_method')
            ->get();

        $authMethodsByUser = [];
        foreach ($authMethodRows as $row) {
            $userId = (string) $row->user_id;
            $method = trim((string) ($row->auth_method ?? ''));
            if ($method === '') {
                continue;
            }

            $authMethodsByUser[$userId] ??= [];
            if (! in_array($method, $authMethodsByUser[$userId], true)) {
                $authMethodsByUser[$userId][] = $method;
            }
        }

        $mfaEnrolledIds = DB::table('mfa_factors')
            ->whereIn('user_id', $userIds)
            ->whereNull('disabled_at')
            ->whereNotNull('verified_at')
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->flip()
            ->all();

        $mfaRequired = $this->mfaService->isTenantMfaPolicyActive();

        $summary = [];
        foreach ($userIds as $userId) {
            $summary[$userId] = [
                'last_active_at' => $lastActiveByUser[$userId] ?? null,
                'auth_methods' => $authMethodsByUser[$userId] ?? [],
                'mfa_enrolled' => isset($mfaEnrolledIds[$userId]),
                'mfa_required' => $mfaRequired,
            ];
        }

        return $summary;
    }
}
