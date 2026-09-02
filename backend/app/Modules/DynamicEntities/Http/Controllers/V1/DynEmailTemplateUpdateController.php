<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEmailTemplate;
use App\Modules\DynamicEntities\Services\DynEmailTemplateService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEmailTemplateUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynEmailTemplate $template,
        DynEmailTemplateService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('email_templates:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'entity_slug' => ['sometimes', 'nullable', 'string', 'max:160'],
            'subject' => ['sometimes', 'string', 'max:500'],
            'body_html' => ['sometimes', 'nullable', 'string'],
            'body_text' => ['sometimes', 'nullable', 'string'],
            'default_to' => ['sometimes', 'nullable', 'string', 'max:500'],
            'cc' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bcc' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->update($template, $data, $actor));
    }
}
