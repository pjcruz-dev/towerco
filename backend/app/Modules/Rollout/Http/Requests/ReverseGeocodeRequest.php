<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseGeocodeRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
