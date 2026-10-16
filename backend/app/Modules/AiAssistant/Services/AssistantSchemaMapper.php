<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Support\AssistantTokenBudget;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Throwable;

/**
 * Fills import column maps and DocExtract fields the heuristic left blank.
 * Runs only when this admin still has assistant tokens.
 */
final class AssistantSchemaMapper
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AssistantTokenBudget $tokens,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  list<array{name?: string, label?: string}>  $fields
     * @param  array<string, string>  $suggested
     * @param  list<array<string, string>>  $samples
     * @return array<string, string>
     */
    public function fillColumnMap(TenantUser $actor, array $headers, array $fields, array $suggested, array $samples): array
    {
        $allowed = [];
        foreach ($fields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $allowed[$name] = trim((string) ($field['label'] ?? $name));
            }
        }
        $gaps = [];
        foreach ($headers as $header) {
            $current = (string) ($suggested[$header] ?? '__ignore__');
            if ($current === '' || $current === '__ignore__') {
                $gaps[] = $header;
            }
        }
        if ($gaps === [] || $allowed === [] || ! $this->tokens->spend($actor)) {
            return $suggested;
        }

        $sample = [];
        foreach (array_slice($samples, 0, 3) as $row) {
            $kept = [];
            foreach ($gaps as $header) {
                $kept[$header] = (string) ($row[$header] ?? '');
            }
            $sample[] = $kept;
        }

        $decoded = $this->ask(
            'Map each CSV header to one field name, or __ignore__. Return a JSON object and nothing else.',
            json_encode([
                'headers' => $gaps,
                'fields' => $allowed,
                'samples' => $sample,
            ], JSON_UNESCAPED_UNICODE),
        );
        if ($decoded === null) {
            return $suggested;
        }

        $filled = 0;
        foreach ($gaps as $header) {
            $choice = $decoded[$header] ?? null;
            if (! is_string($choice)) {
                continue;
            }
            $choice = trim($choice);
            if ($choice === '__ignore__' || isset($allowed[$choice])) {
                $suggested[$header] = $choice;
                $filled++;
            }
        }
        $this->audit($actor, 'assistant.import.map', 'Mapped '.$filled.' import column'.($filled === 1 ? '' : 's').'.', [
            'headers' => $gaps,
            'map' => array_intersect_key($suggested, array_flip($gaps)),
        ]);

        return $suggested;
    }

    /**
     * @param  list<array{key?: string, label?: string}>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function fillExtractedFields(TenantUser $actor, array $fields, string $text, array $values): array
    {
        $text = trim($text);
        if ($text === '') {
            return $values;
        }
        $gaps = [];
        foreach ($fields as $field) {
            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $current = $values[$key] ?? null;
            if ($current === null || $current === '') {
                $gaps[$key] = trim((string) ($field['label'] ?? $key));
            }
        }
        if ($gaps === [] || ! $this->tokens->spend($actor)) {
            return $values;
        }

        $decoded = $this->ask(
            'Fill each field from the document text. Return a JSON object of field key to string. Omit a key you cannot see in the text.',
            json_encode([
                'fields' => $gaps,
                'text' => mb_substr($text, 0, 6000),
            ], JSON_UNESCAPED_UNICODE),
        );
        if ($decoded === null) {
            return $values;
        }

        $filled = 0;
        foreach ($gaps as $key => $label) {
            unset($label);
            $value = $decoded[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $values[$key] = mb_substr(trim($value), 0, 500);
            $filled++;
        }
        $this->audit($actor, 'assistant.doc_extract.map', 'Filled '.$filled.' extracted field'.($filled === 1 ? '' : 's').'.', [
            'fields' => array_keys($gaps),
        ]);

        return $values;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ask(string $system, string $user): ?array
    {
        try {
            $completion = $this->llm->complete(new LlmPrompt(system: $system, user: $user));
        } catch (Throwable) {
            return null;
        }

        $text = trim($completion->answer);
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
     * @param  array<string, mixed>  $metadata
     */
    private function audit(TenantUser $actor, string $action, string $summary, array $metadata): void
    {
        try {
            $this->activity->record(
                module: 'ai_assistant',
                action: $action,
                summary: $summary,
                entityType: 'assistant_schema_map',
                entityLabel: $summary,
                actor: $actor,
                metadata: array_merge($metadata, [
                    'actor_email' => (string) ($actor->email ?? ''),
                ]),
                category: 'data_change',
                severity: 'low',
            );
        } catch (Throwable) {
            // The map still stands when the audit table is unavailable.
        }
    }
}
