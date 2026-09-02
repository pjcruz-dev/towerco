<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class SqlDumpReader
{
    /**
     * @return list<array<string, string|null>>
     */
    public function extractInsertRows(string $sql, string $table): array
    {
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'`\s*\(([^)]+)\)\s*VALUES\s*\((.*?)\);/is';
        if (! preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $rows = [];
        foreach ($matches as $match) {
            $columns = array_map(
                static fn (string $c): string => trim($c, " `\t\n\r"),
                explode(',', $match[1])
            );
            $values = $this->splitSqlValues($match[2]);
            if (count($columns) !== count($values)) {
                continue;
            }
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = $this->unquoteSqlValue($values[$i]);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function readFile(string $dumpPath): string
    {
        if (! is_readable($dumpPath)) {
            throw new \InvalidArgumentException('Dump file is not readable: '.$dumpPath);
        }

        $sql = file_get_contents($dumpPath);
        if ($sql === false) {
            throw new \RuntimeException('Unable to read dump file.');
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    private function splitSqlValues(string $valuesCsv): array
    {
        $out = [];
        $current = '';
        $inQuote = false;
        $len = strlen($valuesCsv);
        for ($i = 0; $i < $len; $i++) {
            $ch = $valuesCsv[$i];
            if ($ch === "'" && ($i === 0 || $valuesCsv[$i - 1] !== '\\')) {
                // Handle escaped quote '' inside SQL strings
                if ($inQuote && ($i + 1) < $len && $valuesCsv[$i + 1] === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }
                $inQuote = ! $inQuote;
                $current .= $ch;
                continue;
            }
            if ($ch === ',' && ! $inQuote) {
                $out[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        if ($current !== '') {
            $out[] = trim($current);
        }

        return $out;
    }

    private function unquoteSqlValue(string $raw): ?string
    {
        $raw = trim($raw);
        if (strtoupper($raw) === 'NULL') {
            return null;
        }
        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            $inner = substr($raw, 1, -1);

            return str_replace(["\\'", "''", '\\\\'], ["'", "'", '\\'], $inner);
        }

        return $raw;
    }
}
