<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Mon–Fri working-day calendar with no tenant holiday table.
 */
final class EApprovalWorkingDaysCalendarFactory
{
    public function make(): WorkingDaysCalendar
    {
        return new WorkingDaysCalendar([]);
    }
}
