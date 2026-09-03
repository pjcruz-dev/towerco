<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services\TenantDatabaseBackup;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs mysqldump / mysql CLI against the shared MySQL server for a single tenant database.
 */
final class TenantDatabaseDumpExecutor
{
    /**
     * Verify mysqldump / mysql CLI and that the tenant database is reachable.
     *
     * @param  'create'|'restore'  $operation
     */
    public function assertReady(string $operation, array $connection): void
    {
        if ($operation === 'create') {
            $this->assertCliAvailable(
                (string) config('toweros.tenant_database_backup.mysqldump_path', 'mysqldump'),
                'mysqldump',
            );
        } else {
            $this->assertCliAvailable(
                (string) config('toweros.tenant_database_backup.mysql_path', 'mysql'),
                'mysql',
            );
        }

        $this->assertSafeDatabaseName($connection['database']);
        $this->pingMysql($connection, requireDatabase: $operation === 'create');
    }

    /**
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    public function pingMysql(array $connection, bool $requireDatabase = true): void
    {
        $mysql = (string) config('toweros.tenant_database_backup.mysql_path', 'mysql');
        $this->assertCliAvailable($mysql, 'mysql');

        $command = [
            $mysql,
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            '-N',
            '-e',
            'SELECT 1',
        ];

        if ($requireDatabase) {
            // Prove the tenant schema exists and is selectable.
            array_splice($command, 4, 0, [$connection['database']]);
        }

        $process = new Process($command, null, [
            'MYSQL_PWD' => $connection['password'],
        ], null, 30);
        $process->run();

        if (! $process->isSuccessful() || ! str_contains(trim($process->getOutput()), '1')) {
            throw new RuntimeException(
                'MySQL preflight failed: '.$this->formatProcessFailure(
                    $process,
                    $process->getErrorOutput(),
                    $mysql,
                    $connection,
                ),
            );
        }
    }

    /**
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    public function dumpToGzipFile(array $connection, string $gzipTargetPath): void
    {
        $this->assertSafeDatabaseName($connection['database']);

        $mysqldump = (string) config('toweros.tenant_database_backup.mysqldump_path', 'mysqldump');
        $timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));
        $this->assertCliAvailable($mysqldump, 'mysqldump');

        $command = [
            $mysqldump,
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            // Avoid PROCESS privilege requirement (common for app DB users in local/Docker).
            '--no-tablespaces',
            $connection['database'],
        ];

        $process = new Process($command, null, [
            'MYSQL_PWD' => $connection['password'],
        ], null, $timeout);

        $process->start();

        $gzip = gzopen($gzipTargetPath, 'wb9');
        if ($gzip === false) {
            $process->stop(1);
            throw new RuntimeException('Could not open gzip target for tenant database dump.');
        }

        $stderr = '';
        try {
            // ITER_KEEP_OUTPUT is required: the default iterator clears stderr/stdout as it yields,
            // which left failures as the empty UI string "mysqldump failed:".
            foreach ($process->getIterator(Process::ITER_KEEP_OUTPUT) as $type => $data) {
                if ($type === Process::ERR) {
                    $stderr .= $data;
                    continue;
                }
                gzwrite($gzip, $data);
            }
        } finally {
            gzclose($gzip);
        }

        if (! $process->isSuccessful()) {
            @unlink($gzipTargetPath);
            throw new RuntimeException(
                'mysqldump failed: '.$this->formatProcessFailure(
                    $process,
                    $stderr,
                    $mysqldump,
                    $connection,
                ),
            );
        }

        if (! is_file($gzipTargetPath) || filesize($gzipTargetPath) === 0) {
            @unlink($gzipTargetPath);
            throw new RuntimeException('mysqldump produced an empty archive.');
        }
    }

    /**
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    public function restoreFromGzipFile(array $connection, string $gzipSourcePath): void
    {
        $this->assertSafeDatabaseName($connection['database']);

        if (! is_file($gzipSourcePath)) {
            throw new RuntimeException('Backup archive not found for restore.');
        }

        $mysql = (string) config('toweros.tenant_database_backup.mysql_path', 'mysql');
        $timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));
        $this->assertCliAvailable($mysql, 'mysql');

        $sqlPath = $gzipSourcePath.'.sql';
        $this->gunzipToFile($gzipSourcePath, $sqlPath);

        try {
            $this->dropAndRecreateDatabase($connection);

            $command = [
                $mysql,
                '--host='.$connection['host'],
                '--port='.(string) $connection['port'],
                '--user='.$connection['username'],
                $connection['database'],
            ];

            // Prefer streaming the SQL file into mysql (avoids loading multi‑MB dumps into PHP memory).
            $process = new Process($command, null, [
                'MYSQL_PWD' => $connection['password'],
            ], null, $timeout);
            $process->setInput(fopen($sqlPath, 'rb') ?: throw new RuntimeException('Could not open decompressed SQL for import.'));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'mysql restore failed: '.$this->formatProcessFailure(
                        $process,
                        $process->getErrorOutput(),
                        $mysql,
                        $connection,
                    ),
                );
            }
        } finally {
            @unlink($sqlPath);
        }
    }

    /**
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    private function dropAndRecreateDatabase(array $connection): void
    {
        $this->assertSafeDatabaseName($connection['database']);

        // Drop fails while other sessions hold the tenant DB (API workers, open tabs).
        try {
            \Illuminate\Support\Facades\DB::purge('tenant');
        } catch (\Throwable) {
            // Tenant connection may not be bootstrapped on the central host.
        }

        $mysql = (string) config('toweros.tenant_database_backup.mysql_path', 'mysql');
        $this->assertCliAvailable($mysql, 'mysql');
        $db = $connection['database'];
        $quoted = str_replace('`', '``', $db);

        $sql = sprintf(
            "SELECT CONCAT('KILL ', id) FROM information_schema.processlist WHERE db = '%s'; DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
            str_replace("'", "''", $db),
            $quoted,
            $quoted,
        );

        $command = [
            $mysql,
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            '-e',
            $sql,
        ];

        $process = new Process($command, null, [
            'MYSQL_PWD' => $connection['password'],
        ], null, 120);
        $process->run();

        // KILL statements may return errors for already-gone sessions; still require CREATE success.
        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput());
            // Retry drop/create alone if KILL noise caused a non-zero exit.
            $retry = new Process([
                $mysql,
                '--host='.$connection['host'],
                '--port='.(string) $connection['port'],
                '--user='.$connection['username'],
                '-e',
                sprintf(
                    'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
                    $quoted,
                    $quoted,
                ),
            ], null, [
                'MYSQL_PWD' => $connection['password'],
            ], null, 120);
            $retry->run();
            if (! $retry->isSuccessful()) {
                throw new RuntimeException(
                    'Could not recreate tenant database before restore: '.trim($retry->getErrorOutput() !== '' ? $retry->getErrorOutput() : $err),
                );
            }
        }
    }

    private function gunzipToFile(string $gzipPath, string $targetPath): void
    {
        $in = gzopen($gzipPath, 'rb');
        if ($in === false) {
            throw new RuntimeException('Could not open gzip backup for decompress.');
        }

        $out = fopen($targetPath, 'wb');
        if ($out === false) {
            gzclose($in);
            throw new RuntimeException('Could not write decompressed SQL.');
        }

        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, 1024 * 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($out, $chunk);
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }

    private function assertCliAvailable(string $binary, string $label): void
    {
        $binary = trim($binary);
        if ($binary === '') {
            throw new RuntimeException("{$label} path is empty. Set TOWEROS_MYSQLDUMP_PATH / TOWEROS_MYSQL_PATH.");
        }

        // Absolute/relative path to a file — verify it exists and is executable-ish.
        if (str_contains($binary, '/') || str_contains($binary, '\\') || preg_match('/\.(exe|bat|cmd)$/i', $binary) === 1) {
            if (! is_file($binary)) {
                throw new RuntimeException(
                    "{$label} binary not found at [{$binary}]. Install MySQL client tools or set TOWEROS_MYSQLDUMP_PATH / TOWEROS_MYSQL_PATH.",
                );
            }

            return;
        }

        // Bare command name — resolve on PATH (where / which).
        $finder = Process::fromShellCommandline(
            PHP_OS_FAMILY === 'Windows' ? 'where '.escapeshellarg($binary) : 'command -v '.escapeshellarg($binary),
        );
        $finder->setTimeout(10);
        $finder->run();
        if (! $finder->isSuccessful() || trim($finder->getOutput()) === '') {
            throw new RuntimeException(
                "{$label} not found on PATH [{$binary}]. Install MySQL client tools in the API/worker environment, or set TOWEROS_MYSQLDUMP_PATH / TOWEROS_MYSQL_PATH.",
            );
        }
    }

    /**
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    private function formatProcessFailure(
        Process $process,
        string $capturedStderr,
        string $binary,
        array $connection,
    ): string {
        $detail = trim($capturedStderr);
        if ($detail === '') {
            $detail = trim($process->getErrorOutput());
        }
        if ($detail === '') {
            $detail = trim($process->getOutput());
        }

        $meta = sprintf(
            'exit=%s; binary=%s; host=%s:%s; db=%s',
            $process->getExitCode() ?? 'null',
            $binary,
            $connection['host'],
            (string) $connection['port'],
            $connection['database'],
        );

        return $detail !== '' ? $detail.' ('.$meta.')' : $meta;
    }

    private function assertSafeDatabaseName(string $database): void
    {
        // Stancl names are typically prefix + tenant UUID (hyphens allowed), e.g. tenantf59b7369-8ad3-...
        if ($database === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $database)) {
            throw new RuntimeException('Refusing unsafe tenant database name.');
        }

        $central = (string) config('database.connections.central.database');
        if ($central !== '' && strcasecmp($database, $central) === 0) {
            throw new RuntimeException('Refusing to dump or restore the central database.');
        }
    }
}
