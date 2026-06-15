<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Http\Controllers\V1;



use App\Core\Http\Controllers\AbstractApiController;

use App\Modules\EApproval\Services\EApprovalFormTemplateService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class EApprovalFormTemplateCustomUpdateController extends AbstractApiController

{

    public function __invoke(Request $request, string $templateId, EApprovalFormTemplateService $service): JsonResponse

    {

        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);



        $payload = $request->validate([

            'name' => ['required', 'string', 'max:255'],

            'description' => ['nullable', 'string'],

            'category' => ['nullable', 'string', 'max:50'],

            'fields' => ['required', 'array', 'min:1'],

            'steps' => ['nullable', 'array'],

        ]);



        $row = $service->upsertTenantTemplate($templateId, $payload);



        return $this->ok($row);

    }

}

