<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssistantConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai_assistant:use') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function title(): string
    {
        return trim((string) $this->validated('title'));
    }
}
