<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use Illuminate\Support\Collection;

final class ProcurementRfqAutoCloseService
{
    public function __construct(
        private readonly ProcurementRfqService $rfqs,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
    ) {}

    /**
     * @return array{rfqs_closed: int, rfqs_scanned: int}
     */
    public function run(): array
    {
        if (! (bool) ($this->scoringPolicy->policy()['auto_close_at_deadline'] ?? true)) {
            return ['rfqs_closed' => 0, 'rfqs_scanned' => 0];
        }

        /** @var Collection<int, ProcurementRfq> $expired */
        $expired = ProcurementRfq::query()
            ->where('status', ProcurementRfqStatus::OPEN)
            ->whereNotNull('bidding_closes_at')
            ->where('bidding_closes_at', '<=', now())
            ->orderBy('bidding_closes_at')
            ->get();

        $closed = 0;

        foreach ($expired as $rfq) {
            $this->rfqs->closeBidding($rfq, null, 'auto_closed');
            $closed++;
        }

        return [
            'rfqs_closed' => $closed,
            'rfqs_scanned' => $expired->count(),
        ];
    }
}
