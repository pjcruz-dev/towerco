<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Http\Controllers\V1;



use App\Core\Http\Controllers\AbstractApiController;

use App\Modules\EApproval\Models\EApprovalForm;

use App\Modules\EApproval\Services\EApprovalFormService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class EApprovalFormRevisionRestoreController extends AbstractApiController

{

    public function __invoke(

        Request $request,

        EApprovalForm $form,

        int $revision,

        EApprovalFormService $service,

    ): JsonResponse {

        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);



        $result = $service->restoreFromRevision($form, $revision, $request->user());



        return $this->ok([

            'form' => $result['form']->toDetailPayload(),

            'warnings' => $result['warnings'],

        ]);

    }

}

