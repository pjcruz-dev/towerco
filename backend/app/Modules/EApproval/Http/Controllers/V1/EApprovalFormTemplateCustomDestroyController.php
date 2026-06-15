<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Http\Controllers\V1;



use App\Core\Http\Controllers\AbstractApiController;

use App\Modules\EApproval\Services\EApprovalFormTemplateService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class EApprovalFormTemplateCustomDestroyController extends AbstractApiController

{

    public function __invoke(Request $request, string $templateId, EApprovalFormTemplateService $service): JsonResponse

    {

        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);



        $service->deleteTenantTemplate($templateId);



        return $this->ok(['deleted' => true]);

    }

}

