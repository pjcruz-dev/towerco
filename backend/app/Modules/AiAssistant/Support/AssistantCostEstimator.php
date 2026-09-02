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
    public static function estimate(?int $promptTokens, ?int $completionTokens): ?array
    {
        if ($promptTokens === null && $completionTokens === null) {
            return null;
        }

        $in = max(0, (int) ($promptTokens ?? 0));
        $out = max(0, (int) ($completionTokens ?? 0));
        $total = $in + $out;
        if ($total <= 0) {
            return null;
        }

        $phpPerUsd = (float) config('ai_assistant.cost.php_per_usd', 58);
        $inputPerMTokUsd = (float) config('ai_assistant.cost.input_usd_per_mtok', 0.075);
        $outputPerMTokUsd = (float) config('ai_assistant.cost.output_usd_per_mtok', 0.30);

        $usd = ($in / 1_000_000) * $inputPerMTokUsd + ($out / 1_000_000) * $outputPerMTokUsd;
        $php = $usd * max(1.0, $phpPerUsd);

        // Band for UI badge (Metacore-style range).
        $low = max(0.01, round($php * 0.7, 2));
        $high = max($low, round($php * 1.6, 2));

        $intensity = match (true) {
            $total >= 4000 => 'high',
            $total >= 1500 => 'medium',
            default => 'low',
        };

        $intensityLabel = match ($intensity) {
            'high' => 'High',
            'medium' => 'Med',
            default => 'Low',
        };

        return [
            'currency' => 'PHP',
            'low' => $low,
            'high' => $high,
            'label' => sprintf('Est: ₱%s - ₱%s (%s)', number_format($low, 2), number_format($high, 2), $intensityLabel),
            'intensity' => $intensity,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }
}
