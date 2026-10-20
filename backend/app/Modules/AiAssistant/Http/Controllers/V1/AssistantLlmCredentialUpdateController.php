<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\TenantLlmCredentialService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssistantLlmCredentialUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantLlmCredentialService $credentials): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('tenant:manage'), 403);

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($credentials->providerIds())],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'clear' => ['sometimes', 'boolean'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $key = array_key_exists('api_key', $data) ? $data['api_key'] : null;
        $hasKey = is_string($key) && trim($key) !== '';

        if ($clear && $hasKey) {
            throw ValidationException::withMessages([
                'api_key' => ['Send a new token or clear the saved token, not both.'],
            ]);
        }

        return $this->ok($credentials->save(
            $user,
            (string) $data['provider'],
            is_string($key) ? $key : null,
            $clear,
        ));
    }
}
