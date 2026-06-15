<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Services;



use App\Modules\EApproval\Support\EApprovalNotificationCategory;

use App\Modules\Notifications\Services\TenantNotificationIndexService;

use App\Modules\Notifications\Support\TenantNotificationModule;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;



final class EApprovalNotificationIndexService

{

    public function __construct(

        private readonly TenantNotificationIndexService $index,

    ) {}



    public function paginate(

        string $userId,

        int $page,

        int $perPage,

        ?string $category = null,

        bool $unreadOnly = false,

    ): LengthAwarePaginator {

        return $this->index->paginate(

            $userId,

            [TenantNotificationModule::E_APPROVAL],

            $page,

            $perPage,

            $category === EApprovalNotificationCategory::ACTION || $category === EApprovalNotificationCategory::UPDATE

                ? $category

                : null,

            $unreadOnly,

            TenantNotificationModule::E_APPROVAL,

        );

    }

}

