<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetrieveAssistantKnowledgeRequest extends FormRequest
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
            'query' => ['required', 'string', 'min:2', 'max:2000'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array{query: string, top_k?: int}
     */
    public function validatedPayload(): array
    {
        /** @var array{query: string, top_k?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
