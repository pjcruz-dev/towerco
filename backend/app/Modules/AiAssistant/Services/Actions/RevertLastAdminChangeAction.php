<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Services\AssistantRevertStore;
use App\Modules\AiAssistant\Support\AssistantAudience;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Tenant administrator only. Lists the last delete or database write and restores it after confirmation.
 */
final class RevertLastAdminChangeAction implements AssistantActionInterface
{
    public function __construct(
        private readonly AssistantRevertStore $revert,
        private readonly TenantActivityLogger $activity,
    ) {}

    public function name(): string
    {
        return 'revert_last_admin_change';
    }

    public function description(): string
    {
        return 'Undo the last confirmed admin delete or database change. Lists every saved row and file, then waits for confirmation. Tenant administrator only.';
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function requiredDomainPermissions(): array
    {
        return [];
    }

    public function plannerArgumentRules(): array
    {
        return [];
    }

    public function argumentRules(): array
    {
        return [];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($question, $args);
        $this->assertTenantAdmin($viewer);
        $snapshot = $this->revert->current();
        $lines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        if ($snapshot === null || $lines === []) {
            throw ValidationException::withMessages([
                'revert' => ['There is no admin change to put back.'],
            ]);
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Confirm revert',
            summary: 'Nothing is restored yet. This puts back the last confirmed admin change.',
            payload: [
                'revert' => true,
            ],
            preview: [
                'records' => array_values(array_map('strval', $lines)),
            ],
            confirmLabel: 'Restore listed data',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        unset($payload);
        $this->assertTenantAdmin($viewer);
        $snapshot = $this->revert->current();
        if ($snapshot === null) {
            throw ValidationException::withMessages([
                'revert' => ['There is no admin change to put back.'],
            ]);
        }

        $tables = is_array($snapshot['tables'] ?? null) ? $snapshot['tables'] : [];
        $files = is_array($snapshot['files'] ?? null) ? $snapshot['files'] : [];
        $restored = 0;

        DB::connection('tenant')->transaction(function () use ($tables, &$restored): void {
            foreach ($tables as $bundle) {
                if (! is_array($bundle)) {
                    continue;
                }
                $table = (string) ($bundle['table'] ?? '');
                $rows = $bundle['rows'] ?? null;
                if ($table === '' || ! is_array($rows) || ! Schema::connection('tenant')->hasTable($table)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row) || $row === []) {
                        continue;
                    }
                    DB::connection('tenant')->table($table)->insert($this->plainRow($row));
                    $restored++;
                }
            }
        });

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }
            $this->revert->restoreFile((string) ($file['trash'] ?? ''), (string) ($file['path'] ?? ''));
        }

        $this->revert->forget();
        $lines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $label = sprintf('Restored %d row%s from the last admin change.', $restored, $restored === 1 ? '' : 's');

        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.change_reverted',
            summary: $label,
            entityType: 'assistant_revert',
            entityLabel: $label,
            actor: $viewer,
            metadata: ['lines' => $lines],
            category: 'data_change',
            severity: 'high',
        );

        return new ActionExecutionResult(
            ok: true,
            entityType: 'assistant_revert',
            entityLabel: $label,
            meta: ['rows' => $restored],
        );
    }

    private function assertTenantAdmin(TenantUser $viewer): void
    {
        if (! AssistantAudience::isTenantAdmin($viewer)) {
            throw ValidationException::withMessages([
                'role' => ['Only the tenant administrator can revert an assistant change.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function plainRow(array $row): array
    {
        $plain = [];
        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $plain[$key] = is_array($value) ? json_encode($value) : $value;
        }

        return $plain;
    }
}
