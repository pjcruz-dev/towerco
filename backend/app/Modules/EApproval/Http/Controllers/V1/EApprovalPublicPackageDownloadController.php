<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Services\EApprovalExternalPackageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalPublicPackageDownloadController
{
    public function __invoke(
        string $token,
        EApprovalExternalPackageService $packages,
    ): StreamedResponse {
        return $packages->streamDownload($token);
    }
}
