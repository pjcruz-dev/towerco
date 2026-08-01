<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_id' => ['sometimes', 'nullable', 'uuid'],
            'module_context' => ['sometimes', 'nullable', 'string', 'max:64'],
            'page_path' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array{
     *   question: string,
     *   conversation_id?: string|null,
     *   module_context?: string|null,
     *   page_path?: string|null
     * }
     */
    public function validatedPayload(): array
    {
        /** @var array{
         *   question: string,
         *   conversation_id?: string|null,
         *   module_context?: string|null,
         *   page_path?: string|null
         * } $validated
         */
        $validated = $this->validated();

        return $validated;
    }
}
