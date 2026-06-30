<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Events;

final class ProcurementDocumentApproved extends ProcurementDocumentEvent
{
    public static function name(): string
    {
        return 'procurement.document.approved';
    }
}
