<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalExternalResubmitTokenService
{
    /**
     * @return array{plain_token: string, revise_url: string, expires_at: string}
     */
    public function mint(EApprovalSubmission $submission): array
    {
        if (! $submission->isExternalSubmission()) {
            throw ValidationException::withMessages([
                'submission' => [__('Resubmit tokens are only for external submissions.')],
            ]);
        }

        $plain = Str::random(48);
        $minutes = max(1, (int) config('e_approval.public_links.resubmit_token_minutes', 10080));
        $expires = now()->addMinutes($minutes);

        $submission->external_resubmit_token_hash = hash('sha256', $plain);
        $submission->external_resubmit_token_expires_at = $expires;
        $submission->save();

        $reviseUrl = app(TenantAppUrlResolver::class)->urlForCurrentTenant(
            '/public/e-approval/revise/'.$submission->id.'?resubmit_token='.urlencode($plain),
        );

        return [
            'plain_token' => $plain,
            'revise_url' => $reviseUrl,
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    public function assertValid(EApprovalSubmission $submission, string $resubmitToken): void
    {
        if (! $submission->isExternalSubmission()) {
            throw ValidationException::withMessages([
                'submission' => [__('Invalid revise session.')],
            ]);
        }

        if ($submission->status !== EApprovalSubmissionStatus::RETURNED) {
            throw ValidationException::withMessages([
                'status' => [__('This submission is not awaiting revision.')],
            ]);
        }

        $hash = $submission->external_resubmit_token_hash;
        $expires = $submission->external_resubmit_token_expires_at;

        if ($hash === null || $expires === null || $expires->isPast()) {
            throw ValidationException::withMessages([
                'resubmit_token' => [__('This revise link is invalid or has expired.')],
            ]);
        }

        if (! hash_equals($hash, hash('sha256', $resubmitToken))) {
            throw ValidationException::withMessages([
                'resubmit_token' => [__('This revise link is invalid or has expired.')],
            ]);
        }
    }

    public function clear(EApprovalSubmission $submission): void
    {
        $submission->external_resubmit_token_hash = null;
        $submission->external_resubmit_token_expires_at = null;
        $submission->save();
    }
}
