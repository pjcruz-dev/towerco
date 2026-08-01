<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForwardGeocodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('project_one:view') === true
            || $this->user()?->can('project_one:rollout:manage') === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
