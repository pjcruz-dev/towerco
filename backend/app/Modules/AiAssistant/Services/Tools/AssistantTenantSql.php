<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs one statement on the current tenant database.
 */
final class AssistantTenantSql
{
    private const MAX_SQL = 12000;

    private const MAX_CELL = 300;

    private ?string $digest = null;

    /**
     * @return array{kind: string, summary: string, rows: list<array<string, mixed>>, affected: int}
     */
    public function run(string $sql, int $maxRows): array
    {
        $connection = DB::connection('tenant');
        $current = (string) $connection->getDatabaseName();
        $statements = $this->statements($sql, $current, $this->foreignDatabases($current));
        $limit = max(1, min(200, $maxRows));

        return $connection->transaction(function () use ($connection, $statements, $limit): array {
            $summaries = [];
            $rows = [];
            $affected = 0;
            $wrote = false;

            foreach ($statements as $statement) {
                $one = $this->executeStatement($connection, $statement, $limit);
                $summaries[] = $one['summary'];
                if ($one['kind'] === 'write') {
                    $wrote = true;
                }
                foreach ($one['rows'] as $row) {
                    $rows[] = $row;
                }
                $affected += $one['affected'];
            }

            $rows = array_slice($rows, 0, $limit);

            return [
                'kind' => $wrote ? 'write' : 'read',
                'summary' => implode(' ', $summaries),
                'rows' => $rows,
                'affected' => $affected,
            ];
        });
    }

    /**
     * @return array{kind: string, summary: string, rows: list<array<string, mixed>>, affected: int}
     */
    private function executeStatement(Connection $connection, string $statement, int $limit): array
    {
        $verb = $this->verb($statement);

        if ($this->isRead($verb)) {
            $rows = array_slice($connection->select($statement), 0, $limit);
            $payload = [];
            foreach ($rows as $row) {
                $payload[] = $this->cells((array) $row);
            }

            return [
                'kind' => 'read',
                'summary' => sprintf('%d row%s.', count($payload), count($payload) === 1 ? '' : 's'),
                'rows' => $payload,
                'affected' => count($payload),
            ];
        }

        if (in_array($verb, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) {
            $count = $connection->affectingStatement($statement);

            return [
                'kind' => 'write',
                'summary' => sprintf('%s affected %d row%s.', $verb, $count, $count === 1 ? '' : 's'),
                'rows' => [],
                'affected' => $count,
            ];
        }

        $connection->unprepared($statement);

        return [
            'kind' => 'write',
            'summary' => $verb.' finished.',
            'rows' => [],
            'affected' => 0,
        ];
    }

    public function digest(): string
    {
        if ($this->digest !== null) {
            return $this->digest;
        }

        try {
            $this->digest = $this->loadDigest();
        } catch (Throwable) {
            $this->digest = '';
        }

        return $this->digest;
    }

    /**
     * @param  list<string>  $foreignDatabases
     */
    public function guard(string $sql, string $currentDatabase, array $foreignDatabases): string
    {
        return implode(";\n", $this->statements($sql, $currentDatabase, $foreignDatabases));
    }

    /**
     * @param  list<string>  $foreignDatabases
     * @return list<string>
     */
    private function statements(string $sql, string $currentDatabase, array $foreignDatabases): array
    {
        $text = $this->stripFences(trim($sql));
        if ($text === '') {
            throw ValidationException::withMessages([
                'sql' => ['SQL is empty.'],
            ]);
        }
        if (mb_strlen($text) > self::MAX_SQL) {
            throw ValidationException::withMessages([
                'sql' => ['SQL is too long.'],
            ]);
        }

        $pieces = $this->splitStatements($text);
        if (count($pieces) > 8) {
            throw ValidationException::withMessages([
                'sql' => ['Send at most 8 statements.'],
            ]);
        }

        foreach ($pieces as $statement) {
            $this->guardOne($statement, $currentDatabase, $foreignDatabases);
        }

        return $pieces;
    }

    /**
     * @param  list<string>  $foreignDatabases
     */
    private function guardOne(string $statement, string $currentDatabase, array $foreignDatabases): void
    {
        if (preg_match('/\b(load_file|into\s+outfile|into\s+dumpfile|load\s+data)\b/i', $statement) === 1) {
            throw ValidationException::withMessages([
                'sql' => ['File reads and file writes are rejected.'],
            ]);
        }

        $verb = $this->verb($statement);
        $allowed = ['SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'CREATE', 'DROP', 'PRAGMA'];
        if (! in_array($verb, $allowed, true)) {
            throw ValidationException::withMessages([
                'sql' => ['That statement is not allowed. A wipe (TRUNCATE, DROP TABLE, or a change with no WHERE) is rejected.'],
            ]);
        }
        if ($verb === 'DROP' && preg_match('/\bindex\b/i', $statement) !== 1) {
            throw ValidationException::withMessages([
                'sql' => ['DROP TABLE is rejected. Change the rows you mean. Do not wipe a table.'],
            ]);
        }
        if (in_array($verb, ['UPDATE', 'DELETE'], true) && preg_match('/\bwhere\b/i', $statement) !== 1) {
            throw ValidationException::withMessages([
                'sql' => ['UPDATE and DELETE need a WHERE clause. A wipe of every row is rejected.'],
            ]);
        }
        if (in_array($verb, ['UPDATE', 'DELETE'], true) && preg_match('/\bwhere\s+(1\s*=\s*1|true|1)\b/i', $statement) === 1) {
            throw ValidationException::withMessages([
                'sql' => ['That WHERE clause matches every row. A wipe is rejected.'],
            ]);
        }
        if (in_array($verb, ['DROP', 'CREATE', 'ALTER'], true) && preg_match('/\b(database|schema|user|view)\b/i', $statement) === 1 && preg_match('/\b(table|index|column)\b/i', $statement) !== 1) {
            throw ValidationException::withMessages([
                'sql' => ['Database, schema, and user statements are rejected.'],
            ]);
        }
        if ($verb === 'CREATE' && preg_match('/\b(database|schema|user)\b/i', $statement) === 1) {
            throw ValidationException::withMessages([
                'sql' => ['Database, schema, and user statements are rejected.'],
            ]);
        }

        $blocked = array_fill_keys(array_map('strtolower', $foreignDatabases), true);
        $current = strtolower($currentDatabase);
        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*[A-Za-z_`]/', $statement, $qualifiers) > 0) {
            foreach ($qualifiers[1] as $left) {
                $name = strtolower((string) $left);
                if ($name === $current || $name === '') {
                    continue;
                }
                if (isset($blocked[$name])) {
                    throw ValidationException::withMessages([
                        'sql' => ['That statement names another database. Stay on this tenant database.'],
                    ]);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $pieces = [];
        $current = '';
        $length = strlen($sql);
        $single = false;
        $double = false;
        $backtick = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === '\\' && ($single || $double)) {
                $current .= $char;
                if ($i + 1 < $length) {
                    $current .= $sql[++$i];
                }

                continue;
            }
            if ($char === "'" && ! $double && ! $backtick) {
                $single = ! $single;
                $current .= $char;

                continue;
            }
            if ($char === '"' && ! $single && ! $backtick) {
                $double = ! $double;
                $current .= $char;

                continue;
            }
            if ($char === '`' && ! $single && ! $double) {
                $backtick = ! $backtick;
                $current .= $char;

                continue;
            }
            if ($char === ';' && ! $single && ! $double && ! $backtick) {
                $piece = trim($current);
                if ($piece !== '') {
                    $pieces[] = $piece;
                }
                $current = '';

                continue;
            }
            $current .= $char;
        }

        $piece = trim($current);
        if ($piece !== '') {
            $pieces[] = $piece;
        }

        return $pieces;
    }

    private function loadDigest(): string
    {
        $connection = DB::connection('tenant');
        $driver = $connection->getDriverName();
        $lines = [];

        if ($driver === 'sqlite') {
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            foreach ($tables as $table) {
                $name = (string) ($table->name ?? '');
                if ($name === '') {
                    continue;
                }
                $columns = [];
                foreach ($connection->select('PRAGMA table_info('.$this->quoteSqlite($name).')') as $column) {
                    $columns[] = (string) ($column->name ?? '');
                }
                $lines[] = $name.': '.implode(', ', array_filter($columns));
            }
        } else {
            $database = (string) $connection->getDatabaseName();
            $columns = $connection->select(
                'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
                [$database],
            );
            $grouped = [];
            foreach ($columns as $column) {
                $table = (string) ($column->TABLE_NAME ?? '');
                $field = (string) ($column->COLUMN_NAME ?? '');
                if ($table === '' || $field === '') {
                    continue;
                }
                $grouped[$table][] = $field;
            }
            foreach ($grouped as $table => $fields) {
                $lines[] = $table.': '.implode(', ', $fields);
            }
        }

        $text = implode("\n", $lines);

        return mb_strlen($text) > 15000 ? mb_substr($text, 0, 14999).'…' : $text;
    }

    /**
     * @return list<string>
     */
    private function foreignDatabases(string $current): array
    {
        $names = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        $central = (string) config('database.connections.central.database', '');
        if ($central !== '') {
            $names[] = $central;
        }

        try {
            foreach (DB::connection('tenant')->select('SHOW DATABASES') as $row) {
                $name = (string) (array_values((array) $row)[0] ?? '');
                if ($name !== '' && strcasecmp($name, $current) !== 0) {
                    $names[] = $name;
                }
            }
        } catch (Throwable) {
            // SQLite has no SHOW DATABASES. The static list still blocks the central name.
        }

        return array_values(array_unique(array_map('strtolower', $names)));
    }

    private function stripFences(string $sql): string
    {
        $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/```$/', '', $sql) ?? $sql;

        return trim($sql);
    }

    private function verb(string $statement): string
    {
        $bare = preg_replace('/^\s*(?:--[^\n]*\n|\/\*.*?\*\/\s*)+/s', '', $statement) ?? $statement;
        $token = strtok(ltrim($bare), " \n\t\r(");

        return strtoupper(is_string($token) ? $token : '');
    }

    private function isRead(string $verb): bool
    {
        return in_array($verb, ['SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'PRAGMA'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function cells(array $row): array
    {
        $clean = [];
        foreach ($row as $key => $value) {
            if (is_string($value) && mb_strlen($value) > self::MAX_CELL) {
                $value = mb_substr($value, 0, self::MAX_CELL - 1).'…';
            }
            $clean[(string) $key] = is_scalar($value) || $value === null ? $value : json_encode($value);
        }

        return $clean;
    }

    private function quoteSqlite(string $name): string
    {
        return "'".str_replace("'", "''", $name)."'";
    }
}
