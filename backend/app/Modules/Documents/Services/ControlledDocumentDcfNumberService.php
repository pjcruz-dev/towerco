<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * DCF numbers are issued when a controlled document is published.
 * Format YYYY-MM-001. The series resets on the first publish of each month.
 */
final class ControlledDocumentDcfNumberService
{
    public function __construct(
        private readonly EApprovalDocumentSequenceService $sequences,
    ) {}

    public function next(?CarbonInterface $at = null): string
    {
        $at ??= now();
        $period = $at->format('Y-m');
        $n = $this->sequences->takeNext('DCF:'.$period);

        return sprintf('%s-%03d', $period, $n);
    }

    /**
     * Blank is allowed. A value must already look like a published number (2026-10-001).
     * Pending rows cannot carry one — the series is issued at publish.
     */
    public function importProblem(?string $raw, bool $pending): ?string
    {
        $number = trim((string) $raw);
        if ($number === '') {
            return null;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{3}$/', $number) !== 1) {
            return __('DCF number must look like 2026-10-001.');
        }

        if ($pending) {
            return __('DCF number is stored only when the document is published.');
        }

        return null;
    }

    public function parseImported(?string $raw): ?string
    {
        $number = trim((string) $raw);
        if ($number === '') {
            return null;
        }

        $problem = $this->importProblem($number, false);
        if ($problem !== null) {
            throw ValidationException::withMessages([
                'dcf_number' => [$problem],
            ]);
        }

        return $number;
    }

    public function reserveImported(string $number): void
    {
        if (preg_match('/^(\d{4}-\d{2})-(\d+)$/', $number, $matches) !== 1) {
            return;
        }

        $this->sequences->noteConsumed('DCF:'.$matches[1], (int) $matches[2]);
    }
}
