<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Http\Controllers\V1;



use App\Core\Http\Controllers\AbstractApiController;

use App\Modules\EApproval\Services\EApprovalFormTemplateService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;



class EApprovalFormTemplateCustomShowController extends AbstractApiController

{

    public function __invoke(Request $request, string $templateId, EApprovalFormTemplateService $service): JsonResponse

    {

        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);



        $definition = $service->tenantTemplateDefinition($templateId);

        if ($definition === null) {

            throw ValidationException::withMessages([

                'template_id' => [__('Tenant template not found.')],

            ]);

        }



        return $this->ok([

            'id' => $templateId,

            'name' => (string) ($definition['name'] ?? ''),

            'description' => $definition['description'] ?? null,

            'category' => (string) ($definition['category'] ?? 'general'),

            'fields' => $definition['fields'] ?? [],

            'steps' => $definition['steps'] ?? [],

            'source' => 'tenant',

            'editable' => true,

        ]);

    }

}

