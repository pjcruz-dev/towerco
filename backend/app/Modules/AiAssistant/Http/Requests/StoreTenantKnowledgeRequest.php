<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantKnowledgeRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'body' => ['required', 'string', 'min:1', 'max:200000'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'module_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'audience' => ['sometimes', 'nullable', 'string', 'max:32'],
            'required_permissions' => ['sometimes', 'nullable', 'array', 'max:50'],
            'required_permissions.*' => ['string', 'max:128'],
            'related_routes' => ['sometimes', 'nullable', 'array', 'max:50'],
            'related_routes.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   body: string,
     *   slug?: string|null,
     *   module_key?: string|null,
     *   audience?: string|null,
     *   required_permissions?: list<string>|null,
     *   related_routes?: list<string>|null
     * }
     */
    public function validatedPayload(): array
    {
        /** @var array{
         *   title: string,
         *   body: string,
         *   slug?: string|null,
         *   module_key?: string|null,
         *   audience?: string|null,
         *   required_permissions?: list<string>|null,
         *   related_routes?: list<string>|null
         * } $validated
         */
        $validated = $this->validated();

        return $validated;
    }
}
