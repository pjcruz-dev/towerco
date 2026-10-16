<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\ToolResult;
use Illuminate\Support\Str;

/**
 * Ordered steps an ask actually performed, for the chat activity trail.
 */
final class AssistantWorkLog
{
    /**
     * @param  list<string>  $chunkTitles
     * @param  list<ToolResult>  $toolResults
     * @param  array<string, mixed>|null  $proposedAction
     * @return list<array{id: string, kind: string, title: string, detail: string|null, status: string}>
     */
    public static function build(
        bool $searched,
        bool $searchFailed,
        array $chunkTitles,
        array $toolResults,
        ?string $modelName,
        ?int $latencyMs,
        bool $modelFailed,
        ?array $proposedAction = null,
        ?string $blockedDetail = null,
    ): array {
        $steps = [];

        if ($searched) {
            $titles = array_values(array_filter(array_map(
                static fn (string $title): string => trim($title),
                $chunkTitles,
            )));
            $steps[] = [
                'id' => 'search',
                'kind' => 'search',
                'title' => $searchFailed ? 'Knowledge search failed' : 'Searched approved knowledge',
                'detail' => $searchFailed
                    ? 'The guide search did not finish.'
                    : ($titles === []
                        ? 'No matching guide.'
                        : count($titles).' '.Str::plural('guide', count($titles))."\n".implode("\n", array_slice($titles, 0, 6))),
                'status' => $searchFailed ? 'failed' : 'done',
            ];
        }

        foreach ($toolResults as $index => $result) {
            $label = self::toolLabel($result->tool);
            $detail = $result->ok
                ? trim((string) ($result->summary ?? ''))
                : trim((string) ($result->error ?? 'The tool did not finish.'));
            if ($result->ok && $result->rowCount > 0) {
                $rows = $result->rowCount.' '.($result->rowCount === 1 ? 'row' : 'rows');
                $detail = $detail !== '' ? $rows.'. '.$detail : $rows;
            }
            if ($detail === '') {
                $detail = $result->ok ? 'Finished.' : 'The tool did not finish.';
            }

            $steps[] = [
                'id' => 'tool-'.$index.'-'.$result->tool,
                'kind' => 'tool',
                'title' => 'Ran '.$label,
                'detail' => self::clip($detail),
                'status' => $result->ok ? 'done' : 'failed',
            ];
        }

        if ($modelName !== null || $modelFailed) {
            $name = trim((string) $modelName);
            $steps[] = [
                'id' => 'model',
                'kind' => 'model',
                'title' => $modelFailed
                    ? 'Model request failed'
                    : 'Asked '.($name !== '' ? $name : 'the model'),
                'detail' => $modelFailed
                    ? 'The model did not return an answer.'
                    : self::latencyDetail($latencyMs),
                'status' => $modelFailed ? 'failed' : 'done',
            ];
        }

        if ($proposedAction !== null) {
            $summary = trim((string) ($proposedAction['summary'] ?? ''));
            $steps[] = [
                'id' => 'action',
                'kind' => 'action',
                'title' => 'Prepared an action for review',
                'detail' => self::clip($summary !== '' ? $summary."\nNothing has been saved yet." : 'Nothing has been saved yet.'),
                'status' => 'done',
            ];
        } elseif ($blockedDetail !== null && trim($blockedDetail) !== '') {
            $steps[] = [
                'id' => 'action',
                'kind' => 'action',
                'title' => 'Could not prepare that action',
                'detail' => self::clip(trim($blockedDetail)),
                'status' => 'failed',
            ];
        }

        return $steps;
    }

    private static function toolLabel(string $tool): string
    {
        $words = trim(str_replace('_', ' ', $tool));

        return $words !== '' ? ucfirst($words) : 'tool';
    }

    private static function latencyDetail(?int $latencyMs): ?string
    {
        if ($latencyMs === null || $latencyMs < 0) {
            return null;
        }
        if ($latencyMs < 1000) {
            return 'Finished in '.$latencyMs.' ms';
        }

        return 'Finished in '.rtrim(rtrim(number_format($latencyMs / 1000, 1, '.', ''), '0'), '.').'s';
    }

    private static function clip(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= 500) {
            return $text;
        }

        return mb_substr($text, 0, 497).'…';
    }
}
