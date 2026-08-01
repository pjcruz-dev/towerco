<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use App\Modules\AiAssistant\Support\AssistantFeedbackRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'uuid'],
            'rating' => ['required', 'string', Rule::in(AssistantFeedbackRating::values())],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{message_id: string, rating: string, comment?: string|null}
     */
    public function validatedPayload(): array
    {
        /** @var array{message_id: string, rating: string, comment?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
