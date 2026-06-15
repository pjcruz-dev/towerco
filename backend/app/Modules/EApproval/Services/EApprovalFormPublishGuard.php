<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Validation\ValidationException;

final class EApprovalFormPublishGuard
{
    public function __construct(
        private readonly EApprovalFormStructureFingerprint $fingerprint,
    ) {}

    public function countOpenSubmissions(EApprovalForm $form): int
    {
        return (int) $form->submissions()
            ->whereIn('status', EApprovalSubmissionStatus::open())
            ->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function warningsFor(EApprovalForm $form, array $payload): array
    {
        $open = $this->countOpenSubmissions($form);
        if ($open === 0) {
            return [];
        }

        $warnings = [
            __(
                ':count open submission(s) exist on this form. In-flight requests keep their submit-time workflow; new requests use the updated definition after publish.',
                ['count' => $open],
            ),
        ];

        if ($this->hasStructuralChange($form, $payload)) {
            $warnings[] = __(
                'You are changing fields or workflow while requests are still open. Prefer cloning to a new form for major upgrades, or confirm you understand the impact.',
            );
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertCanApply(EApprovalForm $form, array $payload, bool $confirmUpgrade, bool $publishing): void
    {
        if (! $publishing && ($payload['status'] ?? $form->status) !== 'published') {
            return;
        }

        $open = $this->countOpenSubmissions($form);
        if ($open === 0) {
            return;
        }

        if (! $this->hasStructuralChange($form, $payload)) {
            return;
        }

        if ($confirmUpgrade) {
            return;
        }

        throw ValidationException::withMessages([
            'confirm_form_upgrade' => [
                __(
                    'This form has :count open submission(s) and you are changing its workflow or field structure. Check "I understand" and save again, or publish a new form instead.',
                    ['count' => $open],
                ),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasStructuralChange(EApprovalForm $form, array $payload): bool
    {
        return $this->fingerprint->fromForm($form) !== $this->fingerprint->fromPayload($payload);
    }
}
