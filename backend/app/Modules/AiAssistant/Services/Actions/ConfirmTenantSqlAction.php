<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Services\AssistantRevertStore;
use App\Modules\AiAssistant\Services\Tools\AssistantTenantSql;
use App\Modules\AiAssistant\Support\AssistantAudience;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenant administrator only. Any database write across this tenant waits here until confirmed.
 */
final class ConfirmTenantSqlAction implements AssistantActionInterface
{
    public function __construct(
        private readonly AssistantTenantSql $sql,
        private readonly AssistantRevertStore $revert,
    ) {}

    public function name(): string
    {
        return 'confirm_tenant_sql';
    }

    public function description(): string
    {
        return 'Prepare an insert, update, or delete on this tenant database and list the rows. Nothing runs until the tenant administrator confirms. Delete is an admin function.';
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
        return [
            'sql' => ['required', 'string', 'min:1', 'max:12000'],
        ];
    }

    public function argumentRules(): array
    {
        return $this->plannerArgumentRules();
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($question);
        $this->assertTenantAdmin($viewer);
        $statement = trim((string) ($args['sql'] ?? ''));
        if ($statement === '' || preg_match('/(?:^|;)\s*(insert|update|delete|replace)\b/i', $statement) !== 1) {
            throw ValidationException::withMessages([
                'sql' => ['That is not a database change.'],
            ]);
        }

        $preview = $this->preview($statement);

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Confirm database change',
            summary: 'Nothing has changed yet. Confirm to run this on the current tenant only.',
            payload: [
                'sql' => $statement,
                'revert' => $preview['revert'],
            ],
            preview: [
                'statement' => $statement,
                'records' => $preview['lines'],
            ],
            moduleKey: null,
            confirmLabel: 'Apply listed change',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $this->assertTenantAdmin($viewer);
        $statement = trim((string) ($payload['sql'] ?? ''));
        $revert = is_array($payload['revert'] ?? null) ? $payload['revert'] : null;
        if ($revert !== null) {
            $this->revert->remember($revert);
        }

        $result = $this->sql->run($statement, 50);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'assistant_sql',
            entityLabel: $result['summary'],
            meta: [
                'affected' => $result['affected'],
            ],
        );
    }

    private function assertTenantAdmin(TenantUser $viewer): void
    {
        if (! AssistantAudience::isTenantAdmin($viewer)) {
            throw ValidationException::withMessages([
                'role' => ['Only the tenant administrator can change or delete tenant data from the assistant.'],
            ]);
        }
    }

    /**
     * @return array{lines: list<string>, revert: array<string, mixed>|null}
     */
    private function preview(string $statement): array
    {
        $lines = [$statement];
        $revert = null;

        if (preg_match('/^\s*(delete\s+from|update)\s+`?([A-Za-z0-9_]+)`?\b([\s\S]*)$/i', $statement, $match) !== 1) {
            $lines[] = 'Insert. Confirm to add the row. Revert cannot put an insert back.';

            return ['lines' => $lines, 'revert' => null];
        }

        $table = $match[2];
        $rest = trim($match[3]);
        $where = '';
        if (preg_match('/\bwhere\b[\s\S]*$/i', $rest, $whereMatch) === 1) {
            $where = trim($whereMatch[0]);
        }
        if ($where === '') {
            $lines[] = 'No WHERE clause. This change is rejected until the rows are named.';

            return ['lines' => $lines, 'revert' => null];
        }

        try {
            $rows = DB::connection('tenant')->select('select * from '.$table.' '.$where.' limit 50');
        } catch (Throwable) {
            $lines[] = 'The matching rows could not be listed. Confirm only if this statement is the change you want.';

            return ['lines' => $lines, 'revert' => null];
        }

        if ($rows === []) {
            $lines[] = 'No rows match. Nothing will change.';

            return ['lines' => $lines, 'revert' => null];
        }

        $stored = [];
        foreach ($rows as $row) {
            $record = (array) $row;
            $stored[] = $record;
            $id = isset($record['id']) ? (string) $record['id'] : '';
            $label = isset($record['name']) ? (string) $record['name'] : (isset($record['document_no']) ? (string) $record['document_no'] : $id);
            $lines[] = trim($table.' '.$label);
        }

        $revert = [
            'lines' => $lines,
            'tables' => [
                ['table' => $table, 'rows' => $stored],
            ],
            'files' => [],
        ];

        return ['lines' => $lines, 'revert' => $revert];
    }
}
