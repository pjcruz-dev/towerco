<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Models\AiAssistantFeedback;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Support\AssistantFeedbackRating;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns thumbs-down feedback into actionable knowledge / routing gap rows.
 */
final class FeedbackGapReportService
{
    /**
     * @return list<array{
     *   feedback_id: string,
     *   rating: string,
     *   comment: string|null,
     *   conversation_id: string,
     *   module_context: string|null,
     *   page_path: string|null,
     *   question: string|null,
     *   answer: string|null,
     *   message_id: string,
     *   created_at: string|null
     * }>
     */
    public function gaps(?Carbon $since = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $query = AiAssistantFeedback::query()
            ->with(['message', 'conversation'])
            ->where('rating', AssistantFeedbackRating::DOWN)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $feedbacks = $query->get();

        // Prefetch prior user messages for all involved conversations in one query,
        // instead of a per-row lookup inside the loop.
        $conversationIds = $feedbacks->pluck('conversation_id')->filter()->unique()->values()->all();
        $userMessagesByConversation = $conversationIds === []
            ? collect()
            : AiMessage::query()
                ->whereIn('conversation_id', $conversationIds)
                ->where('role', AssistantMessageRole::USER)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'conversation_id', 'created_at', 'content'])
                ->groupBy(static fn (AiMessage $message): string => (string) $message->conversation_id);

        $rows = [];
        foreach ($feedbacks as $feedback) {
            $message = $feedback->message;
            $conversation = $feedback->conversation;
            if (! $message instanceof AiMessage) {
                continue;
            }

            $candidates = $userMessagesByConversation->get((string) $message->conversation_id);

            $rows[] = [
                'feedback_id' => (string) $feedback->id,
                'rating' => (string) $feedback->rating,
                'comment' => $feedback->comment,
                'conversation_id' => (string) $feedback->conversation_id,
                'module_context' => $conversation?->module_context,
                'page_path' => $conversation?->page_path,
                'question' => $this->priorUserQuestionFrom($candidates, $message),
                'answer' => $this->truncate((string) $message->content, 400),
                'message_id' => (string) $message->id,
                'created_at' => $feedback->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $gaps
     * @return array{total: int, by_module: array<string, int>, sample_questions: list<string>}
     */
    public function summarize(array $gaps): array
    {
        $byModule = [];
        $questions = [];

        foreach ($gaps as $gap) {
            $module = is_string($gap['module_context'] ?? null) && $gap['module_context'] !== ''
                ? $gap['module_context']
                : '(none)';
            $byModule[$module] = ($byModule[$module] ?? 0) + 1;

            if (is_string($gap['question'] ?? null) && $gap['question'] !== '' && count($questions) < 10) {
                $questions[] = $gap['question'];
            }
        }

        arsort($byModule);

        return [
            'total' => count($gaps),
            'by_module' => $byModule,
            'sample_questions' => $questions,
        ];
    }

    /**
     * Pick the latest user message at or before the assistant reply from a prefetched,
     * created_at-desc ordered candidate collection.
     *
     * @param  Collection<int, AiMessage>|null  $candidates
     */
    private function priorUserQuestionFrom(?Collection $candidates, AiMessage $assistantMessage): ?string
    {
        if ($candidates === null || $assistantMessage->created_at === null) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate->created_at === null
                || $candidate->created_at->greaterThan($assistantMessage->created_at)) {
                continue;
            }

            $content = trim((string) $candidate->content);
            if ($content !== '') {
                return $this->truncate($content, 240);
            }
        }

        return null;
    }

    private function truncate(string $text, int $max): string
    {
        $normalized = trim(preg_replace("/\s+/u", ' ', $text) ?? $text);

        return mb_strlen($normalized) <= $max
            ? $normalized
            : rtrim(mb_substr($normalized, 0, $max)).'…';
    }
}
