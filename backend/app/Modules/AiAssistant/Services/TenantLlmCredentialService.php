<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Models\AiAssistantSetting;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class TenantLlmCredentialService
{
    /**
     * @var array<string, array{label: string, token: bool, column: string|null, config: string|null, model: string}>
     */
    private const PROVIDERS = [
        'cursor' => [
            'label' => 'Cursor',
            'token' => true,
            'column' => 'cursor_api_key',
            'config' => 'ai_assistant.cursor.api_key',
            'model' => 'ai_assistant.cursor.model',
        ],
        'gemini' => [
            'label' => 'Gemini',
            'token' => true,
            'column' => 'gemini_api_key',
            'config' => 'ai_assistant.gemini.api_key',
            'model' => 'ai_assistant.gemini.chat_model',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'token' => true,
            'column' => 'openai_api_key',
            'config' => 'ai_assistant.openai.api_key',
            'model' => 'ai_assistant.openai.chat_model',
        ],
        'bedrock' => [
            'label' => 'Amazon Bedrock',
            'token' => false,
            'column' => null,
            'config' => null,
            'model' => 'ai_assistant.bedrock.chat_model_id',
        ],
    ];

    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return list<string>
     */
    public function providerIds(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function normalizeProvider(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'cursor', 'cursor_ai' => 'cursor',
            'gemini', 'google', 'google_ai', 'ai_studio' => 'gemini',
            'openai', 'chatgpt' => 'openai',
            'bedrock' => 'bedrock',
            default => '',
        };
    }

    public function effectiveProvider(): string
    {
        $selected = $this->normalizeProvider((string) ($this->current()?->llm_provider ?? ''));
        if ($selected !== '') {
            return $selected;
        }

        $fromEnv = $this->normalizeProvider((string) config('ai_assistant.llm_provider', 'cursor'));

        return $fromEnv !== '' ? $fromEnv : 'cursor';
    }

    public function apiKeyFor(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null || $definition['token'] !== true || $definition['column'] === null) {
            return '';
        }

        $stored = $this->storedKey($definition['column']);
        if ($stored !== null) {
            return $stored;
        }

        return trim((string) config((string) $definition['config'], ''));
    }

    /**
     * @return array{
     *     provider: string,
     *     provider_source: string,
     *     providers: list<array{id: string, label: string, uses_token: bool, configured: bool, source: string, model: string, api_key: string|null}>
     * }
     */
    public function snapshot(): array
    {
        $selected = $this->normalizeProvider((string) ($this->current()?->llm_provider ?? ''));

        return [
            'provider' => $this->effectiveProvider(),
            'provider_source' => $selected !== '' ? 'tenant' : 'environment',
            'providers' => array_values(array_map(
                fn (string $id): array => $this->providerSnapshot($id, self::PROVIDERS[$id]),
                array_keys(self::PROVIDERS),
            )),
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     provider_source: string,
     *     providers: list<array{id: string, label: string, uses_token: bool, configured: bool, source: string, model: string, api_key: string|null}>
     * }
     */
    public function save(TenantUser $actor, string $provider, ?string $apiKey, bool $clear): array
    {
        $provider = $this->normalizeProvider($provider);
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            $provider = 'cursor';
            $definition = self::PROVIDERS['cursor'];
        }

        $beforeProvider = $this->effectiveProvider();
        $beforeConfigured = $this->configuredMap();
        $row = $this->currentOrNew();
        $row->llm_provider = $provider;

        if ($definition['token'] === true && is_string($definition['column'])) {
            if ($clear) {
                $row->{$definition['column']} = null;
            } elseif (is_string($apiKey) && trim($apiKey) !== '') {
                $row->{$definition['column']} = trim($apiKey);
            }
        }

        $row->updated_by_user_id = (string) $actor->id;
        $row->save();

        $afterConfigured = $this->configuredMap();
        $changes = [
            'provider' => ['from' => $beforeProvider, 'to' => $this->effectiveProvider()],
        ];
        foreach ($afterConfigured as $id => $configured) {
            $changes[$id.'_configured'] = [
                'from' => $beforeConfigured[$id] ?? false,
                'to' => $configured,
            ];
        }

        $this->activity->record(
            module: 'ai_assistant',
            action: 'llm_credential.updated',
            summary: 'Updated the workspace AI model settings',
            entityType: 'ai_assistant_settings',
            entityId: (string) AiAssistantSetting::SINGLETON_ID,
            actor: $actor,
            changes: $changes,
        );

        return $this->snapshot();
    }

    /**
     * @param  array{label: string, token: bool, column: string|null, config: string|null, model: string}  $definition
     * @return array{id: string, label: string, uses_token: bool, configured: bool, source: string, model: string, api_key: string|null}
     */
    private function providerSnapshot(string $id, array $definition): array
    {
        if ($definition['token'] !== true) {
            return [
                'id' => $id,
                'label' => $definition['label'],
                'uses_token' => false,
                'configured' => true,
                'source' => 'environment',
                'model' => trim((string) config($definition['model'], '')),
                'api_key' => null,
            ];
        }

        $stored = $definition['column'] !== null ? $this->storedKey($definition['column']) : null;
        $environment = trim((string) config((string) $definition['config'], ''));
        $source = 'missing';
        if ($stored !== null) {
            $source = 'tenant';
        } elseif ($environment !== '') {
            $source = 'environment';
        }

        return [
            'id' => $id,
            'label' => $definition['label'],
            'uses_token' => true,
            'configured' => $source !== 'missing',
            'source' => $source,
            'model' => trim((string) config($definition['model'], '')),
            'api_key' => $source === 'tenant' ? $stored : ($source === 'environment' ? $environment : null),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function configuredMap(): array
    {
        $map = [];
        foreach ($this->snapshot()['providers'] as $provider) {
            if ($provider['uses_token']) {
                $map[$provider['id']] = $provider['source'] === 'tenant';
            }
        }

        return $map;
    }

    private function storedKey(string $column): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        try {
            $plain = $this->current()?->getAttribute($column);
        } catch (DecryptException $exception) {
            Log::warning('Tenant AI token could not be decrypted.', [
                'tenant_id' => tenancy()->initialized ? (string) tenant('id') : null,
                'column' => $column,
                'exception' => $exception::class,
            ]);

            return null;
        }

        if (! is_string($plain)) {
            return null;
        }

        $plain = trim($plain);

        return $plain !== '' ? $plain : null;
    }

    private function tableReady(): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        return Schema::hasTable('ai_assistant_settings');
    }

    private function current(): ?AiAssistantSetting
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = AiAssistantSetting::query()->find(AiAssistantSetting::SINGLETON_ID);

        return $row instanceof AiAssistantSetting ? $row : null;
    }

    private function currentOrNew(): AiAssistantSetting
    {
        return $this->current() ?? new AiAssistantSetting([
            'id' => AiAssistantSetting::SINGLETON_ID,
        ]);
    }
}
