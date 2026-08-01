<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use RuntimeException;

/**
 * Amazon Bedrock Converse adapter for chat completions.
 * Requires aws/aws-sdk-php when AI_ASSISTANT_LLM_PROVIDER=bedrock.
 */
final class BedrockLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $region,
        private readonly string $modelId,
        private readonly int $maxTokens = 1024,
        private readonly float $temperature = 0.2,
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $clientClass = 'Aws\\BedrockRuntime\\BedrockRuntimeClient';
        if (! class_exists($clientClass)) {
            throw new RuntimeException(
                'Bedrock LLM requires aws/aws-sdk-php. Install it or set AI_ASSISTANT_LLM_PROVIDER=local.',
            );
        }

        $started = hrtime(true);

        /** @var object $client */
        $client = new $clientClass([
            'version' => 'latest',
            'region' => $this->region,
        ]);

        $result = $client->converse([
            'modelId' => $this->modelId,
            'system' => [
                ['text' => $prompt->system],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['text' => $prompt->user],
                    ],
                ],
            ],
            'inferenceConfig' => [
                'maxTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ]);

        $answer = $this->extractText($result);
        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $insufficient = $prompt->chunks === []
            || str_contains(mb_strtolower($answer), 'do not have enough')
            || str_contains(mb_strtolower($answer), 'not enough approved');

        return new LlmCompletionResult(
            answer: $answer,
            modelName: $this->modelId,
            promptTokens: isset($usage['inputTokens']) ? (int) $usage['inputTokens'] : null,
            completionTokens: isset($usage['outputTokens']) ? (int) $usage['outputTokens'] : null,
            latencyMs: (int) max(0, (hrtime(true) - $started) / 1_000_000),
            insufficientContext: $insufficient,
            suggestedFollowups: [],
        );
    }

    public function modelName(): string
    {
        return $this->modelId;
    }

    private function extractText(mixed $result): string
    {
        $output = is_array($result) ? ($result['output'] ?? null) : null;
        $message = is_array($output) ? ($output['message'] ?? null) : null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;
        if (! is_array($content)) {
            throw new RuntimeException('Bedrock converse response did not include message content.');
        }

        $parts = [];
        foreach ($content as $block) {
            if (is_array($block) && isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        $text = trim(implode("\n", $parts));
        if ($text === '') {
            throw new RuntimeException('Bedrock converse response text was empty.');
        }

        return $text;
    }
}
