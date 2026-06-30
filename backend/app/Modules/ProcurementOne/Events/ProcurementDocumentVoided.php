<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Events;

final class ProcurementDocumentVoided extends ProcurementDocumentEvent
{
    public static function name(): string
    {
        return 'procurement.document.voided';
    }
}
