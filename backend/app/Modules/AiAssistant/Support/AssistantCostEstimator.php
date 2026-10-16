<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Rough PHP cost estimate from token usage (config-driven pricing).
 */
final class AssistantCostEstimator
{
    /**
     * @return array{
     *   currency: string,
     *   low: float,
     *   high: float,
     *   label: string,
     *   intensity: 'low'|'medium'|'high',
     *   prompt_tokens: int|null,
     *   completion_tokens: int|null
     * }|null
     */
    /**
     * @param  string|null  $promptText  Used when providers omit usage (e.g. Cursor) — ~4 chars/token heuristic
     */
    public static function estimate(
        ?int $promptTokens,
        ?int $completionTokens,
        ?string $promptText = null,
        ?string $answerText = null,
        ?string $modelId = null,
    ): ?array {
        $in = $promptTokens;
        $out = $completionTokens;

        if ($in === null && $out === null) {
            $hasText = ($promptText !== null && $promptText !== '')
                || ($answerText !== null && $answerText !== '');
            if (! $hasText) {
                return null;
            }
            // Rough heuristic when the provider does not return token counts.
            $in = max(1, (int) ceil(mb_strlen((string) $promptText) / 4));
            $out = max(1, (int) ceil(mb_strlen((string) $answerText) / 4));
        }

        $in = max(0, (int) ($in ?? 0));
        $out = max(0, (int) ($out ?? 0));
        $total = $in + $out;
        if ($total <= 0) {
            return null;
        }

        $phpPerUsd = (float) config('ai_assistant.cost.php_per_usd', 58);
        [$inputPerMTokUsd, $outputPerMTokUsd] = $modelId !== null && trim($modelId) !== ''
            ? AssistantModelPricing::usdPerMillion($modelId)
            : [
                (float) config('ai_assistant.cost.input_usd_per_mtok', 0.075),
                (float) config('ai_assistant.cost.output_usd_per_mtok', 0.30),
            ];

        $usd = ($in / 1_000_000) * $inputPerMTokUsd + ($out / 1_000_000) * $outputPerMTokUsd;
        $php = $usd * max(1.0, $phpPerUsd);
        $intensity = match (true) {
            $total >= 4000 => 'high',
            $total >= 1500 => 'medium',
            default => 'low',
        };

        return self::band($in, $out, $php, $intensity);
    }

    /**
     * @return array{
     *   currency: string,
     *   low: float,
     *   high: float,
     *   label: string,
     *   intensity: 'low'|'medium'|'high',
     *   prompt_tokens: int|null,
     *   completion_tokens: int|null
     * }
     */
    public static function fromTokens(string $modelId, int $promptTokens, int $completionTokens): array
    {
        $estimate = self::estimate($promptTokens, $completionTokens, null, null, $modelId);

        return $estimate ?? self::band(0, 0, 0.0, 'low');
    }

    /**
     * @return array{
     *   currency: string,
     *   low: float,
     *   high: float,
     *   label: string,
     *   intensity: 'low'|'medium'|'high',
     *   prompt_tokens: int|null,
     *   completion_tokens: int|null
     * }
     */
    /**
     * @param  'low'|'medium'|'high'  $intensity
     */
    private static function band(int $in, int $out, float $php, string $intensity): array
    {
        $intensityLabel = match ($intensity) {
            'high' => 'High',
            'medium' => 'Med',
            default => 'Low',
        };

        $low = max(0.01, round($php * 0.7, 2));
        $high = max($low, round($php * 1.6, 2));

        return [
            'currency' => 'PHP',
            'low' => $low,
            'high' => $high,
            'label' => sprintf('Est: ₱%s - ₱%s (%s)', number_format($low, 2), number_format($high, 2), $intensityLabel),
            'intensity' => $intensity,
            'prompt_tokens' => $in,
            'completion_tokens' => $out,
        ];
    }
}
