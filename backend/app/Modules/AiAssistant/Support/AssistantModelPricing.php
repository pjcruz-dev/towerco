<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Rough list rates so the picker can order models and show a PHP estimate
 * before a reply. Not an invoice. Unknown ids use the mid rate.
 */
final class AssistantModelPricing
{
    /**
     * USD per 1M tokens: [input, output].
     *
     * @return array{0: float, 1: float}
     */
    public static function usdPerMillion(string $modelId): array
    {
        $id = strtolower(trim($modelId));

        foreach (self::tiers() as $tier) {
            foreach ($tier['needles'] as $needle) {
                if ($needle !== '' && str_contains($id, $needle)) {
                    return [$tier['input'], $tier['output']];
                }
            }
        }

        return [1.25, 5.0];
    }

    /**
     * @param  list<string>  $modelIds
     * @return list<string>
     */
    public static function sortHighestFirst(array $modelIds): array
    {
        $ranked = $modelIds;
        usort($ranked, static function (string $a, string $b): int {
            $byPrice = self::typicalPhp($b) <=> self::typicalPhp($a);
            if ($byPrice !== 0) {
                return $byPrice;
            }

            return strcasecmp($a, $b);
        });

        return array_values($ranked);
    }

    /**
     * Preview for a short ask (about 2,000 in / 800 out), same band shape as a real reply.
     *
     * @return array{currency: string, low: float, high: float, label: string, intensity: 'low'|'medium'|'high', prompt_tokens: int|null, completion_tokens: int|null}
     */
    public static function preview(string $modelId): array
    {
        return AssistantCostEstimator::fromTokens($modelId, 2000, 800);
    }

    /**
     * @param  list<string>  $modelIds
     * @return array<string, array{currency: string, low: float, high: float, label: string, intensity: 'low'|'medium'|'high', prompt_tokens: int|null, completion_tokens: int|null}>
     */
    public static function previews(array $modelIds): array
    {
        $out = [];
        foreach ($modelIds as $id) {
            $out[$id] = self::preview($id);
        }

        return $out;
    }

    private static function typicalPhp(string $modelId): float
    {
        return self::preview($modelId)['high'];
    }

    /**
     * @return list<array{needles: list<string>, input: float, output: float}>
     */
    private static function tiers(): array
    {
        return [
            ['needles' => ['opus', 'fable', 'gpt-5.6', 'sol', 'terra'], 'input' => 15.0, 'output' => 75.0],
            ['needles' => ['sonnet', 'grok-4.7', 'gpt-5.5', 'codex', 'gpt-5'], 'input' => 3.0, 'output' => 15.0],
            ['needles' => ['grok-4.6', 'composer', 'haiku'], 'input' => 1.25, 'output' => 5.0],
            ['needles' => ['flash', 'grok-4.5', 'default', 'auto'], 'input' => 0.15, 'output' => 0.60],
        ];
    }
}
