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
     * @param  array{host: string, port: int|string, username: string, password: string, database: string}  $connection
     */
    public function dumpToGzipFile(array $connection, string $gzipTargetPath): void
    {
        $this->assertSafeDatabaseName($connection['database']);

        $mysqldump = (string) config('toweros.tenant_database_backup.mysqldump_path', 'mysqldump');
        $timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));

        $command = [
            $mysqldump,
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
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

        try {
            foreach ($process as $type => $data) {
                if ($type === Process::ERR) {
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
                'mysqldump failed: '.trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput()),
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

            $process = new Process($command, null, [
                'MYSQL_PWD' => $connection['password'],
            ], null, $timeout);
            $sql = file_get_contents($sqlPath);
            if ($sql === false) {
                throw new RuntimeException('Could not read decompressed SQL for import.');
            }
            $process->setInput($sql);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'mysql restore failed: '.trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput()),
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
