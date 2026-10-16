<?php

declare(strict_types=1);

namespace Tests\Support\AiAssistant;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\OperationDecision;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;

/**
 * Test double for the API model. Planner prompts are turned into catalog JSON
 * by the existing routers so feature tests do not call Cursor.
 */
final class DeterministicCatalogLlm implements LlmProviderInterface
{
    public function __construct(
        private readonly AssistantActionRouter $actions,
        private readonly AssistantToolRouter $tools,
        private readonly AssistantToolFallbackPlanner $fallback,
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        if (str_contains($prompt->system, 'Return one JSON object')) {
            $question = $this->questionFrom($prompt->user);
            $decision = $this->decide($question, str_contains($prompt->user, 'PRIOR_RESULTS:'));
            $encoded = json_encode([
                'decision' => $decision->decision,
                'name' => $decision->name,
                'arguments' => $decision->arguments,
            ], JSON_UNESCAPED_UNICODE);

            return new LlmCompletionResult(
                answer: is_string($encoded) ? $encoded : '{"decision":"answer"}',
                modelName: $this->modelName(),
            );
        }

        $summaries = $this->liveSummaries($prompt->user);

        return new LlmCompletionResult(
            answer: $summaries !== [] ? implode("\n", $summaries) : 'The assistant is ready.',
            modelName: $this->modelName(),
            suggestedFollowups: ['How do I submit an E-Forms request?'],
        );
    }

    public function modelName(): string
    {
        return 'deterministic-catalog';
    }

    private function decide(string $question, bool $readsAlreadyRan): OperationDecision
    {
        if ($readsAlreadyRan) {
            return OperationDecision::answer();
        }

        $match = $this->actions->match($question, null);
        if ($match !== null) {
            return new OperationDecision('write', $match['action'], $match['args']);
        }

        $plan = $this->tools->plan($question, null);
        if (! $plan->useTools()) {
            $fallback = $this->fallback->plan($question, null, []);
            if ($fallback !== null && $fallback->useTools()) {
                $plan = $fallback;
            }
        }

        $call = $plan->calls[0] ?? null;
        if ($plan->useTools() && $call !== null) {
            return new OperationDecision('read', $call->tool, $call->args);
        }

        return OperationDecision::answer();
    }

    private function questionFrom(string $user): string
    {
        $marker = "QUESTION:\n";
        $pos = strrpos($user, $marker);
        if ($pos === false) {
            return trim($user);
        }

        return trim(substr($user, $pos + strlen($marker)));
    }

    /**
     * @return list<string>
     */
    private function liveSummaries(string $user): array
    {
        if (preg_match('/BEGIN_LIVE_SYSTEM_DATA\n(.*)\nEND_LIVE_SYSTEM_DATA/s', $user, $block) !== 1) {
            return [];
        }

        if (preg_match_all('/"summary"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $block[1], $matches) !== 1) {
            return [];
        }

        $summaries = [];
        foreach ($matches[1] as $summary) {
            $text = stripcslashes($summary);
            if ($text !== '') {
                $summaries[] = $text;
            }
        }

        return $summaries;
    }
}
