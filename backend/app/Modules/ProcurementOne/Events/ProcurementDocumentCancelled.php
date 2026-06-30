<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Events;

final class ProcurementDocumentCancelled extends ProcurementDocumentEvent
{
    public static function name(): string
    {
        return 'procurement.document.cancelled';
    }
}
