<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAssistantActionRequest extends FormRequest
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
            'proposal_id' => ['required', 'uuid'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array{proposal_id: string, payload?: array<string, mixed>|null}
     */
    public function validatedPayload(): array
    {
        /** @var array{proposal_id: string, payload?: array<string, mixed>|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
