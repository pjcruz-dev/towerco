<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Services\EApprovalSubmissionShareLinkService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalPublicSharedAttachmentDownloadController
{
    public function __invoke(
        string $token,
        string $attachment,
        EApprovalSubmissionShareLinkService $service,
    ): StreamedResponse {
        return $service->streamAttachment($token, $attachment);
    }
}
