<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Services;



use App\Modules\EApproval\Models\EApprovalSubmission;

use App\Modules\EApproval\Support\EApprovalNotificationCategory;

use App\Modules\Identity\Models\TenantUser;

use App\Modules\Notifications\Services\TenantNotificationService;

use App\Modules\Notifications\Support\TenantNotificationModule;



final class EApprovalInAppNotificationService

{

    public function __construct(

        private readonly TenantNotificationService $tenantNotifications,

    ) {}



    public function notify(

        string $userId,

        string $type,

        ?string $submissionId,

        string $message,

        ?EApprovalSubmission $submission = null,

        ?TenantUser $actor = null,

        ?string $bodyPreview = null,

    ): void {

        if ($submission === null && $submissionId !== null && $submissionId !== '') {

            $submission = EApprovalSubmission::query()

                ->with('form:id,name')

                ->find($submissionId);

        } elseif ($submission !== null && ! $submission->relationLoaded('form')) {

            $submission->loadMissing('form:id,name');

        }



        $documentNo = $submission?->document_no;

        $formName = $submission?->form?->name;



        $this->tenantNotifications->notify(

            userId: $userId,

            module: TenantNotificationModule::E_APPROVAL,

            type: $type,

            message: $message,

            subjectType: $submissionId !== null && $submissionId !== '' ? 'submission' : null,

            subjectId: $submissionId,

            contextPrimary: $documentNo,

            contextSecondary: $formName,

            bodyPreview: $bodyPreview,

            href: EApprovalNotificationCategory::hrefFor($type, $submissionId),

            actor: $actor,

            category: EApprovalNotificationCategory::forType($type),

        );

    }



    public function unreadCount(string $userId): int

    {

        return $this->tenantNotifications->unreadCount($userId, [TenantNotificationModule::E_APPROVAL]);

    }



    public function markRead(string $userId, string $notificationId): void

    {

        $this->tenantNotifications->markRead($userId, $notificationId, TenantNotificationModule::E_APPROVAL);

    }



    public function markAllRead(string $userId, ?string $category = null): void

    {

        $this->tenantNotifications->markAllRead(

            $userId,

            [TenantNotificationModule::E_APPROVAL],

            $category,

            TenantNotificationModule::E_APPROVAL,

        );

    }

}

