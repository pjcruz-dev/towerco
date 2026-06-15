<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalPublicFormLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicFormShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $token, EApprovalPublicFormLinkService $links): JsonResponse
    {
        $accessPassword = $request->query('access_password') ?? $request->input('access_password');
        if (is_string($accessPassword) && strlen($accessPassword) > 128) {
            $accessPassword = null;
        }

        $link = $links->resolveLinkForRead($token);
        if ($link->password_hash !== null && ($accessPassword === null || $accessPassword === '')) {
            $form = $link->form;

            return $this->ok([
                'requires_password' => true,
                'sponsor_label' => $link->sponsor?->name,
                'plan_features' => $links->planFeaturesSnapshot(),
                'approver_options' => [],
                'form' => [
                    'id' => $form ? (string) $form->id : '',
                    'name' => $form?->name ?? '',
                    'description' => $form?->description,
                    'brand_logo_url' => $form?->brand_logo_url,
                    'brand_primary_color' => $form?->brand_primary_color,
                    'fields' => [],
                ],
            ]);
        }

        $link = $links->resolveActiveLink($token, is_string($accessPassword) ? $accessPassword : null);

        return $this->ok($links->publicFormPayload($link));
    }
}
