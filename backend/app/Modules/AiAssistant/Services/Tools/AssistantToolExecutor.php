<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Executes allowlisted tools with RBAC, module gates, row limits, timeout, and audit.
 */
final class AssistantToolExecutor
{
    public function __construct(
        private readonly AssistantToolRegistry $registry,
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  list<ToolCallRequest>  $calls
     * @return list<ToolResult>
     */
    public function executeMany(TenantUser $viewer, array $calls): array
    {
        $maxTools = max(1, (int) config('ai_assistant.tools.max_per_request', 2));
        $maxRows = max(1, (int) config('ai_assistant.tools.max_rows', 10));
        $timeoutSeconds = max(1, (int) config('ai_assistant.tools.timeout_seconds', 5));

        $results = [];
        foreach (array_slice($calls, 0, $maxTools) as $call) {
            $results[] = $this->executeOne($viewer, $call, $maxRows, $timeoutSeconds);
        }

        return $results;
    }

    public function executeOne(
        TenantUser $viewer,
        ToolCallRequest $call,
        ?int $maxRows = null,
        ?int $timeoutSeconds = null,
    ): ToolResult {
        $maxRows ??= max(1, (int) config('ai_assistant.tools.max_rows', 10));
        $timeoutSeconds ??= max(1, (int) config('ai_assistant.tools.timeout_seconds', 5));

        if (! $this->registry->has($call->tool)) {
            return new ToolResult(
                tool: $call->tool,
                ok: false,
                error: 'Tool is not allowlisted.',
            );
        }

        $tool = $this->registry->get($call->tool);
        $module = $tool->requiredModule();

        if ($module !== null && $module !== '') {
            $enabled = $this->enabledModules->resolveForCurrentTenant();
            if (! in_array($module, $enabled, true)) {
                $denied = new ToolResult(
                    tool: $tool->name(),
                    ok: false,
                    error: "Module '{$module}' is not enabled for this tenant.",
                    moduleKey: $module,
                );
                $this->audit($viewer, $denied, 'module_disabled');

                return $denied;
            }
        }

        foreach ($tool->requiredPermissions() as $permission) {
            if (! $viewer->can($permission)) {
                $denied = new ToolResult(
                    tool: $tool->name(),
                    ok: false,
                    error: "Missing permission: {$permission}",
                    moduleKey: $module,
                );
                $this->audit($viewer, $denied, 'permission_denied');

                return $denied;
            }
        }

        try {
            $validated = Validator::make($call->args, $tool->argumentRules())->validate();
        } catch (ValidationException $e) {
            $denied = new ToolResult(
                tool: $tool->name(),
                ok: false,
                error: 'Invalid tool arguments: '.collect($e->errors())->flatten()->first(),
                moduleKey: $module,
            );
            $this->audit($viewer, $denied, 'validation_failed');

            return $denied;
        }

        $started = hrtime(true);

        try {
            // Soft timeout: record overruns; hard kill would require process isolation.
            $result = $tool->execute($viewer, $validated, $maxRows);
            $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);

            if ($elapsedMs > ($timeoutSeconds * 1000)) {
                Log::warning('ai_assistant.tool.timeout_soft', [
                    'tool' => $tool->name(),
                    'elapsed_ms' => $elapsedMs,
                    'limit_ms' => $timeoutSeconds * 1000,
                    'tenant_id' => tenant()?->getTenantKey(),
                ]);
            }

            $this->audit($viewer, $result, $result->ok ? 'ok' : 'tool_error', $elapsedMs);

            return $result;
        } catch (Throwable $e) {
            $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
            Log::warning('ai_assistant.tool.failed', [
                'tool' => $tool->name(),
                'error' => $e->getMessage(),
                'tenant_id' => tenant()?->getTenantKey(),
                'user_id' => (string) $viewer->id,
            ]);

            $failed = new ToolResult(
                tool: $tool->name(),
                ok: false,
                error: 'Tool execution failed.',
                moduleKey: $module,
            );
            $this->audit($viewer, $failed, 'exception', $elapsedMs);

            return $failed;
        }
    }

    private function audit(TenantUser $viewer, ToolResult $result, string $outcome, ?int $latencyMs = null): void
    {
        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.tool.invoke',
            summary: $result->tool,
            entityType: 'ai_assistant_tool',
            entityId: $result->tool,
            entityLabel: $result->tool,
            actor: $viewer,
            metadata: [
                'outcome' => $outcome,
                'ok' => $result->ok,
                'error' => $result->error,
                'row_count' => $result->rowCount,
                'module_key' => $result->moduleKey,
                'latency_ms' => $latencyMs,
                'tenant_id' => tenant()?->getTenantKey(),
            ],
        );
    }
}
