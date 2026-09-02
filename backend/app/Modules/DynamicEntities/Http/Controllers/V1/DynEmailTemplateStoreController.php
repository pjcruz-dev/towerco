<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynEmailTemplateService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEmailTemplateStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynEmailTemplateService $service): JsonResponse
    {
        abort_unless($request->user()?->can('email_templates:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'entity_slug' => ['nullable', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:500'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'default_to' => ['nullable', 'string', 'max:500'],
            'cc' => ['nullable', 'string', 'max:500'],
            'bcc' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->ok($service->create($data, $actor), 201);
    }
}
