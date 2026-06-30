<?php

declare(strict_types=1);

return [
    'public_quotes' => [
        'rate_limit_per_minute' => (int) env('PROCUREMENT_PUBLIC_QUOTES_RATE_LIMIT_PER_MINUTE', 30),
    ],
    'rfq_reminders' => [
        'days_before_close' => array_map(
            static fn ($day) => (int) $day,
            array_filter(explode(',', (string) env('PROCUREMENT_RFQ_REMINDER_DAYS', '3,1'))),
        ) ?: [3, 1],
    ],
];
