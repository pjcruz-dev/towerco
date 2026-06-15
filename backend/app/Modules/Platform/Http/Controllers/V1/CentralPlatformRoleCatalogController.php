<?php



declare(strict_types=1);



namespace App\Modules\Platform\Http\Controllers\V1;



use App\Core\Http\Controllers\AbstractApiController;

use App\Modules\Platform\Support\PlatformRoleCatalog;

use Illuminate\Http\JsonResponse;



final class CentralPlatformRoleCatalogController extends AbstractApiController

{

    public function __invoke(PlatformRoleCatalog $catalog): JsonResponse

    {

        return $this->ok([

            'roles' => $catalog->roles(),

            'permissions' => $catalog->allPermissions(),

        ]);

    }

}


