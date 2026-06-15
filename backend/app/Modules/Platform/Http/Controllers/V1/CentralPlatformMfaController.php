<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformAuthAuditService;
use App\Modules\Platform\Services\PlatformAuthService;
use App\Modules\Platform\Services\PlatformMfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CentralPlatformMfaController extends AbstractApiController
{
    public function verify(
        Request $request,
        PlatformAuthService $auth,
        PlatformMfaService $mfa,
    ): JsonResponse {
        $data = $request->validate([
            'login_session_id' => ['required', 'uuid'],
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $session = DB::table('platform_login_sessions')->where('id', $data['login_session_id'])->first();
        if (! $session) {
            abort(404, __('Login session not found.'));
        }

        /** @var User $user */
        $user = User::query()->findOrFail($session->user_id);
        $mfa->resolveLoginSession($data['login_session_id'], (string) $user->id);

        return $this->ok($auth->completeMfaAndIssueToken(
            $user,
            $data['login_session_id'],
            $data['challenge_id'],
            $data['code'],
        ));
    }

    public function recovery(
        Request $request,
        PlatformAuthService $auth,
        PlatformMfaService $mfa,
    ): JsonResponse {
        $data = $request->validate([
            'login_session_id' => ['required', 'uuid'],
            'recovery_code' => ['required', 'string', 'max:32'],
        ]);

        $session = DB::table('platform_login_sessions')->where('id', $data['login_session_id'])->first();
        if (! $session) {
            abort(404, __('Login session not found.'));
        }

        /** @var User $user */
        $user = User::query()->findOrFail($session->user_id);

        return $this->ok($auth->completeRecoveryAndIssueToken(
            $user,
            $data['login_session_id'],
            $data['recovery_code'],
        ));
    }

    public function enrollStart(
        Request $request,
        PlatformMfaService $mfa,
        PlatformAuthAuditService $audit,
    ): JsonResponse {
        $data = $request->validate([
            'login_session_id' => ['required', 'uuid'],
        ]);

        $session = DB::table('platform_login_sessions')->where('id', $data['login_session_id'])->first();
        if (! $session) {
            abort(404, __('Login session not found.'));
        }

        /** @var User $user */
        $user = User::query()->findOrFail($session->user_id);
        $mfa->resolveLoginSession($data['login_session_id'], (string) $user->id);

        $setup = $mfa->startEnrollment($user);
        $audit->log('platform.auth.mfa.enrollment.started', $user);

        return $this->ok($setup);
    }

    public function enrollComplete(
        Request $request,
        PlatformAuthService $auth,
        PlatformMfaService $mfa,
    ): JsonResponse {
        $data = $request->validate([
            'login_session_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $session = DB::table('platform_login_sessions')->where('id', $data['login_session_id'])->first();
        if (! $session) {
            abort(404, __('Login session not found.'));
        }

        /** @var User $user */
        $user = User::query()->findOrFail($session->user_id);

        return $this->ok($auth->completeEnrollmentAndIssueToken(
            $user,
            $data['login_session_id'],
            $data['code'],
        ));
    }

    public function status(Request $request, PlatformMfaService $mfa): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok([
            'platform_mfa_required' => $mfa->isPolicyRequired(),
            'platform_mfa_enrolled' => $mfa->userHasVerifiedFactor($user),
        ]);
    }

    public function authenticatedEnrollStart(Request $request, PlatformMfaService $mfa, PlatformAuthAuditService $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $setup = $mfa->startEnrollment($user);
        $audit->log('platform.auth.mfa.enrollment.started', $user);

        return $this->ok($setup);
    }

    public function authenticatedEnrollComplete(Request $request, PlatformMfaService $mfa, PlatformAuthAuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $recoveryCodes = $mfa->completeEnrollment($user, $data['code']);
        $audit->log('platform.auth.mfa.enrollment.completed', $user);

        return $this->ok(['recovery_codes' => $recoveryCodes]);
    }

    public function regenerateRecoveryCodes(Request $request, PlatformMfaService $mfa, PlatformAuthAuditService $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $mfa->userHasVerifiedFactor($user)) {
            abort(422, __('MFA is not enrolled for this operator.'));
        }

        $codes = $mfa->regenerateRecoveryCodes($user);
        $audit->log('platform.auth.mfa.recovery_codes.regenerated', $user, [], 'high');

        return $this->ok(['recovery_codes' => $codes]);
    }
}
