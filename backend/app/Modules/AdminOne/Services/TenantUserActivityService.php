<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TenantUserActivityService
{
    /**
     * @return list<array{
     *   id: string,
     *   event: string,
     *   label: string,
     *   risk_level: string,
     *   ip_address: string|null,
     *   context: array<string, mixed>|null,
     *   created_at: string|null
     * }>
     */
    public function listForUser(TenantUser $user, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        $rows = DB::table('auth_audit_logs')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'event',
                'risk_level',
                'ip_address',
                'context',
                'created_at',
            ]);

        return $rows->map(function ($row): array {
            $event = (string) ($row->event ?? '');
            $context = null;
            if ($row->context !== null && $row->context !== '') {
                $decoded = json_decode((string) $row->context, true);
                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'id' => (string) $row->id,
                'event' => $event,
                'label' => $this->eventLabel($event),
                'risk_level' => (string) ($row->risk_level ?? 'low'),
                'ip_address' => $row->ip_address !== null ? (string) $row->ip_address : null,
                'context' => $context,
                'created_at' => $row->created_at !== null
                    ? Carbon::parse((string) $row->created_at)->toIso8601String()
                    : null,
            ];
        })->values()->all();
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'auth.login.success' => __('Signed in'),
            'auth.login.failed' => __('Sign-in failed'),
            'auth.logout' => __('Signed out'),
            'auth.logout_all' => __('Signed out all sessions'),
            'auth.session.revoked' => __('Session revoked'),
            'auth.admin.sessions_revoked' => __('All sessions revoked by administrator'),
            default => str_replace(['auth.', '_'], ['', ' '], $event),
        };
    }
}
