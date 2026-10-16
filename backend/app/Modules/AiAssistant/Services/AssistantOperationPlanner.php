<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\OperationDecision;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\Tools\AssistantTenantSql;
use App\Modules\AiAssistant\Support\AssistantAdminPrompt;
use App\Modules\AiAssistant\Support\CursorLlmProvider;

/**
 * Asks the model which catalog operation to run. Unknown names are dropped.
 */
final class AssistantOperationPlanner
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AssistantTenantSql $tenantSql,
    ) {}

    /**
     * @param  list<array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}>  $catalog
     * @param  list<ConversationTurn>  $history
     * @param  list<ToolResult>  $toolResults
     * @param  (callable(string): void)|null  $onWaiting
     * @param  (callable(array{kind: string, text: string, name?: string, status?: string}): void)|null  $onDelta
     */
    public function decide(
        string $question,
        ?string $moduleContext,
        array $catalog,
        array $history,
        array $toolResults,
        ?string $preferredModel,
        ?callable $onWaiting = null,
        ?callable $onDelta = null,
        string $roleNote = '',
    ): OperationDecision {
        if ($catalog === []) {
            return OperationDecision::answer();
        }

        if ($this->llm instanceof CursorLlmProvider && $onWaiting !== null) {
            $this->llm->listenWhileWaiting($onWaiting);
        }
        if ($this->llm instanceof CursorLlmProvider && $onDelta !== null) {
            $this->llm->listenToDeltas(function (array $delta) use ($onDelta): void {
                if (($delta['kind'] ?? '') === 'assistant') {
                    return;
                }
                $onDelta($delta);
            });
        }

        try {
            $system = AssistantAdminPrompt::forPlanner($question);
            if ($roleNote !== '') {
                $system .= "\n\n".$roleNote;
            }
            $completion = $this->llm->complete(new LlmPrompt(
                system: $system,
                user: $this->userPrompt($question, $catalog, $history, $toolResults),
                modelOverride: $preferredModel,
            ));
        } finally {
            if ($this->llm instanceof CursorLlmProvider) {
                $this->llm->listenWhileWaiting(null);
                $this->llm->listenToDeltas(null);
            }
        }

        return $this->parse($completion->answer, $catalog);
    }

    /**
     * @param  list<array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}>  $catalog
     */
    public function parse(string $raw, array $catalog): OperationDecision
    {
        $decoded = $this->decode($raw);
        if ($decoded === null) {
            return OperationDecision::answer();
        }

        $decision = isset($decoded['decision']) && is_string($decoded['decision'])
            ? strtolower(trim($decoded['decision']))
            : 'answer';

        if (! in_array($decision, ['read', 'write', 'answer'], true)) {
            return OperationDecision::answer();
        }

        $message = isset($decoded['message']) && is_string($decoded['message']) && trim($decoded['message']) !== ''
            ? trim($decoded['message'])
            : null;

        if ($decision === 'answer') {
            return OperationDecision::answer($message);
        }

        $name = isset($decoded['name']) && is_string($decoded['name']) ? trim($decoded['name']) : '';
        $entry = null;
        foreach ($catalog as $candidate) {
            if ($candidate['name'] === $name && $candidate['kind'] === $decision) {
                $entry = $candidate;
                break;
            }
        }

        if ($entry === null && $name === 'execute_sql') {
            foreach ($catalog as $candidate) {
                if ($candidate['name'] === 'execute_sql') {
                    $entry = $candidate;
                    $decision = 'read';
                    break;
                }
            }
        }

        if ($entry === null) {
            return OperationDecision::answer($message);
        }

        $allowed = array_map(
            static fn (array $field): string => $field['name'],
            $entry['arguments'],
        );

        return new OperationDecision(
            $decision,
            $name,
            $this->arguments(is_array($decoded['arguments'] ?? null) ? $decoded['arguments'] : [], $allowed),
            $message,
            $this->phrase($decoded['purpose'] ?? null),
            $this->phrase($decoded['note'] ?? null, 400),
        );
    }

    /**
     * @param  list<array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}>  $catalog
     * @param  list<ConversationTurn>  $history
     * @param  list<ToolResult>  $toolResults
     */
    private function userPrompt(string $question, array $catalog, array $history, array $toolResults): string
    {
        $lines = ['CATALOG:'];
        foreach ($catalog as $entry) {
            $args = [];
            foreach ($entry['arguments'] as $field) {
                $args[] = $field['name'].($field['required'] ? ' required' : ' optional');
            }
            $module = $entry['module'] ?? 'any';
            $lines[] = sprintf(
                '- %s %s (%s): %s',
                $entry['kind'],
                $entry['name'],
                $module,
                $this->clip($entry['description']),
            );
            $lines[] = '  arguments: '.($args === [] ? 'none' : implode(', ', $args));
        }

        $sqlEnabled = false;
        foreach ($catalog as $entry) {
            if ($entry['name'] === 'execute_sql') {
                $sqlEnabled = true;
                break;
            }
        }
        if ($sqlEnabled) {
            $schema = $this->tenantSql->digest();
            $lines[] = '';
            $lines[] = 'TENANT_SCHEMA:';
            $lines[] = $schema !== '' ? $schema : '(schema unavailable)';
        }

        if ($history !== []) {
            $lines[] = '';
            $lines[] = 'CONVERSATION:';
            foreach (array_slice($history, -6) as $turn) {
                $lines[] = $turn->role.': '.$this->clip($turn->content, 500);
            }
        }

        if ($toolResults !== []) {
            $lines[] = '';
            $lines[] = 'PRIOR_RESULTS:';
            foreach ($toolResults as $result) {
                $detail = $result->ok
                    ? (string) $result->summary
                    : (string) $result->error;
                if ($result->tool === 'execute_sql' && $result->data !== []) {
                    $encoded = json_encode($result->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $detail .= ' '.(is_string($encoded) ? $encoded : '');
                }
                $lines[] = $result->ok
                    ? $result->tool.': '.$this->clip($detail, $result->tool === 'execute_sql' ? 6000 : 800)
                    : $result->tool.' failed: '.$this->clip($detail, $result->tool === 'execute_sql' ? 2000 : 300);
            }
        }

        $lines[] = '';
        $lines[] = 'QUESTION:';
        $lines[] = $question;

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $fenced) === 1) {
            $text = $fenced[1];
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<mixed>  $arguments
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function arguments(array $arguments, array $allowed): array
    {
        $clean = [];
        foreach ($arguments as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $clean[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $clean;
    }

    private function phrase(mixed $value, int $limit = 80): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/', ' ', trim($value, " \t\n\r\"'")) ?? '');
        if ($text === '' || preg_match('/\b(select|insert|update|delete|truncate|drop)\b/i', $text) === 1) {
            return null;
        }

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }

    private function clip(string $text, int $limit = 240): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }
}
