<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Support\AssistantToolAnswer;
use Tests\TestCase;

final class AssistantToolAnswerTest extends TestCase
{
    public function test_a_count_row_is_the_reply(): void
    {
        $answer = AssistantToolAnswer::fromResults([
            new ToolResult(
                tool: 'execute_sql',
                ok: true,
                data: [
                    'rows' => [
                        ['published_count' => 4],
                    ],
                ],
                summary: '1 row.',
                rowCount: 1,
            ),
        ]);

        $this->assertSame('Published count: 4', $answer);
    }

    public function test_a_failed_read_does_not_invent_an_answer(): void
    {
        $answer = AssistantToolAnswer::fromResults([
            new ToolResult(
                tool: 'execute_sql',
                ok: false,
                error: 'That SQL was rejected.',
            ),
        ]);

        $this->assertNull($answer);
    }
}
