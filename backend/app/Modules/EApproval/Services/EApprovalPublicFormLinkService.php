<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalPublicFormLink;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalPublicFormLinkService
{
    public function __construct(
        private readonly TenantAppUrlResolver $tenantUrls,
        private readonly EApprovalPlanFeaturesService $planFeatures,
        private readonly EApprovalAssignableUsersService $assignableUsers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForForm(EApprovalForm $form): array
    {
        return EApprovalPublicFormLink::query()
            ->where('form_id', $form->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (EApprovalPublicFormLink $link) => $link->toAdminRow())
            ->all();
    }

    /**
     * @param  array{
     *     label?: string|null,
     *     sponsor_user_id: string,
     *     expires_at?: string|null,
     *     max_submissions?: int|null,
     *     password?: string|null
     * }  $input
     * @return array{link: array<string, mixed>, public_url: string, token: string}
     */
    public function create(EApprovalForm $form, array $input, TenantUser $actor): array
    {
        $this->assertFormAllowsPublicLinks($form);

        $sponsor = TenantUser::query()->find($input['sponsor_user_id']);
        if ($sponsor === null) {
            throw ValidationException::withMessages([
                'sponsor_user_id' => [__('Internal sponsor user not found.')],
            ]);
        }

        $password = isset($input['password']) ? trim((string) $input['password']) : '';
        $passwordHash = $password !== '' ? Hash::make($password) : null;

        $link = EApprovalPublicFormLink::query()->create([
            'form_id' => $form->id,
            'label' => isset($input['label']) ? trim((string) $input['label']) : null,
            'token_hash' => '',
            'password_hash' => $passwordHash,
            'sponsor_user_id' => $sponsor->id,
            'created_by_user_id' => $actor->id,
            'is_enabled' => true,
            'expires_at' => $input['expires_at'] ?? null,
            'max_submissions' => $input['max_submissions'] ?? null,
        ]);

        [, $plainToken, $tokenHash] = $this->generateTokenPair((string) $link->id);
        $link->token_hash = $tokenHash;
        $link->save();

        $encoded = $this->encodeAccessToken($plainToken);

        return [
            'link' => $link->fresh(['sponsor'])->toAdminRow(),
            'public_url' => $this->publicUrl($plainToken),
            'token' => $encoded,
        ];
    }

    /**
     * @return array{link: array<string, mixed>, public_url: string, token: string}
     */
    public function rotate(EApprovalPublicFormLink $link, TenantUser $actor): array
    {
        $link->loadMissing('form');
        $form = $link->form;
        if ($form === null) {
            throw ValidationException::withMessages(['link' => [__('Form not found.')]]);
        }

        $this->assertFormAllowsPublicLinks($form);

        [, $plainToken, $tokenHash] = $this->generateTokenPair((string) $link->id);
        $link->token_hash = $tokenHash;
        $link->revoked_at = null;
        $link->is_enabled = true;
        $link->save();

        $encoded = $this->encodeAccessToken($plainToken);

        return [
            'link' => $link->fresh(['sponsor'])->toAdminRow(),
            'public_url' => $this->publicUrl($plainToken),
            'token' => $encoded,
        ];
    }

    public function revoke(EApprovalPublicFormLink $link): EApprovalPublicFormLink
    {
        $link->is_enabled = false;
        $link->revoked_at = now();
        $link->save();

        return $link->fresh(['sponsor']);
    }

    public function resolveLinkForRead(string $accessToken): EApprovalPublicFormLink
    {
        $link = $this->resolveLinkByToken($accessToken);
        $link->loadMissing(['form', 'sponsor']);

        if (! $link->isActive()) {
            throw ValidationException::withMessages([
                'token' => [__('This public form link is no longer available.')],
            ]);
        }

        $form = $link->form;
        if ($form === null || $form->status !== 'published') {
            throw ValidationException::withMessages([
                'token' => [__('This form is not available for public submission.')],
            ]);
        }

        return $link;
    }

    public function resolveActiveLink(string $accessToken, ?string $accessPassword = null): EApprovalPublicFormLink
    {
        $link = $this->resolveLinkForRead($accessToken);

        if ($link->password_hash !== null) {
            if ($accessPassword === null || $accessPassword === '' || ! Hash::check($accessPassword, (string) $link->password_hash)) {
                throw ValidationException::withMessages([
                    'access_password' => [__('A valid access password is required.')],
                ]);
            }
        }

        return $link;
    }

    public function touchUsed(EApprovalPublicFormLink $link): void
    {
        $link->last_used_at = now();
        $link->save();
    }

    public function incrementSubmissions(EApprovalPublicFormLink $link): void
    {
        $link->increment('submissions_count');
        $this->touchUsed($link);
    }

    public function publicUrl(string $plainToken): string
    {
        return $this->tenantUrls->urlForCurrentTenant('/public/e-approval/'.$this->encodeAccessToken($plainToken));
    }

    public function encodeAccessToken(string $plainToken): string
    {
        return rtrim(strtr(base64_encode($plainToken), '+/', '-_'), '=');
    }

    public function decodeAccessToken(string $accessToken): string
    {
        $normalized = strtr($accessToken, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false || $decoded === '') {
            throw ValidationException::withMessages([
                'token' => [__('Invalid public form link.')],
            ]);
        }

        return $decoded;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function generateTokenPair(?string $linkId = null): array
    {
        $id = $linkId ?? (string) Str::uuid();
        $secret = Str::random(48);
        $plain = $id.'.'.$secret;

        return [$id, $plain, hash('sha256', $secret)];
    }

    private function resolveLinkByToken(string $accessToken): EApprovalPublicFormLink
    {
        $plainToken = $this->decodeAccessToken($accessToken);
        $parts = explode('.', trim($plainToken), 2);
        if (count($parts) !== 2 || ! Str::isUuid($parts[0]) || $parts[1] === '') {
            throw ValidationException::withMessages([
                'token' => [__('Invalid public form link.')],
            ]);
        }

        /** @var EApprovalPublicFormLink|null $link */
        $link = EApprovalPublicFormLink::query()
            ->with(['form.fields', 'form.workflowTemplate.steps', 'sponsor'])
            ->find($parts[0]);

        if ($link === null || ! hash_equals((string) $link->token_hash, hash('sha256', $parts[1]))) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid public form link.')],
            ]);
        }

        return $link;
    }

    public function assertFormAllowsPublicLinks(EApprovalForm $form): void
    {
        if ($form->status !== 'published') {
            throw ValidationException::withMessages([
                'form' => [__('Publish the form before creating a public link.')],
            ]);
        }

        $form->loadMissing('workflowTemplate.steps');
        $steps = $form->workflowTemplate?->steps ?? collect();

        foreach ($steps as $step) {
            if ($step instanceof EApprovalWorkflowStep && $step->approver_type === 'manager') {
                throw ValidationException::withMessages([
                    'workflow' => [__(
                        'Public links require fixed approvers (user or approver field). Manager-based steps are not supported for external submitters.',
                    )],
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function publicFormPayload(EApprovalPublicFormLink $link): array
    {
        $form = $link->form;
        if ($form === null) {
            throw ValidationException::withMessages(['token' => [__('Form not found.')]]);
        }

        $detail = $form->toDetailPayload();
        unset($detail['submissions_count'], $detail['revisions']);

        return [
            'requires_password' => $link->password_hash !== null,
            'sponsor_label' => $link->sponsor?->name,
            'plan_features' => $this->planFeaturesSnapshot(),
            'approver_options' => $this->approverOptionsForPublicForm($detail['fields'] ?? []),
            'form' => [
                'id' => $detail['id'],
                'name' => $detail['name'],
                'description' => $detail['description'],
                'brand_logo_url' => $detail['brand_logo_url'],
                'brand_primary_color' => $detail['brand_primary_color'],
                'fields' => $detail['fields'],
            ],
        ];
    }

    /**
     * @return array{plan_tier: string, file_uploads: bool, max_file_fields: int|null}
     */
    public function planFeaturesSnapshot(): array
    {
        $tenantId = (string) (tenant('id') ?? '');

        return $this->planFeatures->snapshot($tenantId !== '' ? $tenantId : null);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array{id: string, label: string}>
     */
    private function approverOptionsForPublicForm(array $fields): array
    {
        $hasApproverField = false;
        foreach ($fields as $field) {
            if (is_array($field) && ($field['type'] ?? '') === 'approver') {
                $hasApproverField = true;
                break;
            }
        }

        if (! $hasApproverField) {
            return [];
        }

        return array_map(
            static fn (array $user): array => [
                'id' => (string) $user['id'],
                'label' => trim((string) ($user['name'] ?? '')).' · '.(string) ($user['email'] ?? ''),
            ],
            $this->assignableUsers->listForPickers(),
        );
    }
}
