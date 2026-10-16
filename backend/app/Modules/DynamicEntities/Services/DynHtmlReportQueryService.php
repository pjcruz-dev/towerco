<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read-only SQL for HTML reports that call AppFramework.ExecuteSQL.
 * One SELECT or WITH. The tenant connection is the only database.
 */
final class DynHtmlReportQueryService
{
    private const MAX_SQL = 12000;

    private const MAX_ROWS = 500;

    /**
     * @return list<array<string, mixed>>
     */
    public function select(string $sql): array
    {
        $text = trim($sql);
        if ($text === '') {
            throw ValidationException::withMessages([
                'sql' => [__('SQL is empty.')],
            ]);
        }
        if (mb_strlen($text) > self::MAX_SQL) {
            throw ValidationException::withMessages([
                'sql' => [__('SQL is too long.')],
            ]);
        }

        $text = rtrim($text, "; \t\n\r");
        if (str_contains($text, ';')) {
            throw ValidationException::withMessages([
                'sql' => [__('Send one SELECT statement.')],
            ]);
        }
        if (preg_match('/^\s*(select|with)\b/i', $text) !== 1) {
            throw ValidationException::withMessages([
                'sql' => [__('Only a SELECT query can run from an HTML report.')],
            ]);
        }
        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke|load_file|into\s+outfile|into\s+dumpfile|load\s+data)\b/i', $text) === 1) {
            throw ValidationException::withMessages([
                'sql' => [__('That query is not a read.')],
            ]);
        }

        $rows = DB::connection('tenant')->select($text);
        $payload = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $payload[] = (array) $row;
        }

        return $payload;
    }
}
