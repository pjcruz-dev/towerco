<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One MySQL statement on the current tenant database. The model writes the SQL.
 */
final class ExecuteSqlTool implements AssistantToolInterface
{
    public function __construct(
        private readonly AssistantTenantSql $sql,
        private readonly TenantActivityLogger $activity,
    ) {}

    public function name(): string
    {
        return 'execute_sql';
    }

    public function description(): string
    {
        return 'Run SQL on this tenant database now. Use when no other operation fits. arguments.sql is the statement. SELECT, INSERT, UPDATE, and DELETE run immediately. UPDATE and DELETE need a WHERE clause. TRUNCATE and DROP TABLE are rejected. Every change is written to the audit log with the signed-in admin.';
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function requiredPermissions(): array
    {
        return ['ai_assistant:actions:execute'];
    }

    public function argumentRules(): array
    {
        return [
            'sql' => ['required', 'string', 'min:1', 'max:12000'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $statement = (string) ($args['sql'] ?? '');

        try {
            $result = $this->sql->run($statement, max($maxRows, 200));
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            $this->audit($viewer, $statement, false, $message !== '' ? $message : 'That SQL was rejected.', null);

            return new ToolResult(
                tool: $this->name(),
                ok: false,
                error: $message !== '' ? $message : 'That SQL was rejected.',
            );
        } catch (Throwable $e) {
            Log::warning('ai_assistant.sql.failed', [
                'error' => $e->getMessage(),
                'tenant_id' => tenant()?->getTenantKey(),
                'user_id' => (string) $viewer->id,
            ]);
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'The statement failed.';
            $this->audit($viewer, $statement, false, $message, null);

            return new ToolResult(
                tool: $this->name(),
                ok: false,
                error: $message,
            );
        }

        $this->audit($viewer, $statement, true, $result['summary'], $result['kind']);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'summary' => $result['summary'],
                'rows' => $result['rows'],
                'affected' => $result['affected'],
            ],
            summary: $result['summary'],
            rowCount: $result['affected'],
        );
    }

    private function audit(TenantUser $viewer, string $statement, bool $ok, string $summary, ?string $kind): void
    {
        $sql = mb_substr(trim($statement), 0, 4000);
        $write = $kind === 'write' || preg_match('/^\s*(insert|update|delete|replace|alter|create)\b/i', $sql) === 1;

        try {
            $this->activity->record(
                module: 'ai_assistant',
                action: $ok ? ($write ? 'assistant.sql.write' : 'assistant.sql.read') : 'assistant.sql.rejected',
                summary: mb_substr($summary, 0, 500),
                entityType: 'assistant_sql',
                entityLabel: mb_substr($sql, 0, 180),
                actor: $viewer,
                metadata: [
                    'sql' => $sql,
                    'ok' => $ok,
                    'kind' => $kind,
                    'actor_email' => (string) ($viewer->email ?? ''),
                ],
                reason: $ok ? null : $summary,
                category: $write ? 'data_change' : 'access',
                severity: $ok ? ($write ? 'medium' : 'low') : 'high',
            );
        } catch (Throwable $e) {
            Log::warning('ai_assistant.sql.audit_failed', [
                'error' => $e->getMessage(),
                'tenant_id' => tenant()?->getTenantKey(),
                'user_id' => (string) $viewer->id,
            ]);
        }
    }
}
