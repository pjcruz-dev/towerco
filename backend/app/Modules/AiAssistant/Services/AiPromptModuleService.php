<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Models\AiPromptModule;
use App\Modules\AiAssistant\Support\AiPromptIntentDetector;
use App\Modules\AiAssistant\Support\AiPromptModuleCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AiPromptModuleService
{
    public function __construct(
        private readonly AiPromptIntentDetector $intents,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(bool $includeBody = false): array
    {
        $this->ensureSeeded();

        return AiPromptModule::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AiPromptModule $m): array => $this->present($m, $includeBody))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(AiPromptModule $module): array
    {
        return $this->present($module, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(AiPromptModule $module, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            $module->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) ($data['description'] ?? ''));
            $module->description = $desc !== '' ? $desc : null;
        }
        if (array_key_exists('body', $data)) {
            $body = (string) $data['body'];
            if (strlen($body) > 500_000) {
                throw ValidationException::withMessages(['body' => 'Prompt body exceeds the 500KB limit.']);
            }
            $module->body = $body;
        }
        if (array_key_exists('is_enabled', $data)) {
            $module->is_enabled = (bool) $data['is_enabled'];
        }
        if (array_key_exists('sort_order', $data) && is_numeric($data['sort_order'])) {
            $module->sort_order = (int) $data['sort_order'];
        }

        $module->updated_by = (string) $actor->id;
        $module->save();

        return $this->present($module, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function resetToDefault(AiPromptModule $module, TenantUser $actor): array
    {
        $defaults = collect(AiPromptModuleCatalog::defaults())->keyBy('key');
        $def = $defaults->get($module->key);
        if (! is_array($def)) {
            throw ValidationException::withMessages(['module' => 'No catalog default for this module.']);
        }

        $module->name = (string) $def['name'];
        $module->filename = (string) $def['filename'];
        $module->description = (string) $def['description'];
        $module->kind = (string) $def['kind'];
        $module->intent_key = $def['intent_key'] !== null ? (string) $def['intent_key'] : null;
        $module->sort_order = (int) $def['sort_order'];
        $module->body = (string) $def['body'];
        $module->is_enabled = true;
        // Null = track catalog defaults again on ensureSeeded sync.
        $module->updated_by = null;
        $module->save();

        return $this->present($module, true);
    }

    /**
     * Assemble modular system instruction for the LLM.
     *
     * @param  list<string>  $historyUserTexts
     */
    public function assembleSystemPrompt(string $question, array $historyUserTexts = [], bool $hasFiles = false): string
    {
        if (! $this->tableReady()) {
            return $this->fallbackCoreOnly();
        }

        $this->ensureSeeded();

        $detected = $this->intents->detect($question, $historyUserTexts, $hasFiles);
        // Empty intents → still load core only (token economy). System/architect only when matched or files attached.

        $modules = AiPromptModule::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $parts = [];
        $usedKeys = [];

        foreach ($modules as $module) {
            if ($module->kind === 'router') {
                continue; // reference-only in admin UI
            }
            if ($module->kind === 'always') {
                $parts[] = trim((string) $module->body);
                $usedKeys[] = $module->key;

                continue;
            }
            $intent = $module->intent_key;
            if ($intent !== null && $intent !== '' && in_array($intent, $detected, true)) {
                $parts[] = trim((string) $module->body);
                $usedKeys[] = $module->key;
            }
        }

        if ($parts === []) {
            return $this->fallbackCoreOnly();
        }

        $header = '### ACTIVE PROMPT MODULES: ['.implode(', ', $usedKeys)."]\n"
            .'### DETECTED INTENTS: ['.(implode(', ', $detected) !== '' ? implode(', ', $detected) : 'none')."]\n\n";

        return $header.implode("\n\n---\n\n", $parts);
    }

    public function ensureSeeded(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $defaults = collect(AiPromptModuleCatalog::defaults())->keyBy('key');

        if (! AiPromptModule::query()->exists()) {
            foreach (AiPromptModuleCatalog::defaults() as $def) {
                AiPromptModule::query()->create([
                    'key' => $def['key'],
                    'name' => $def['name'],
                    'filename' => $def['filename'],
                    'description' => $def['description'],
                    'kind' => $def['kind'],
                    'intent_key' => $def['intent_key'],
                    'sort_order' => $def['sort_order'],
                    'body' => $def['body'],
                    'is_enabled' => true,
                    'is_system' => true,
                    'updated_by' => null,
                ]);
            }

            return;
        }

        // Insert any new catalog keys that are missing on this tenant.
        foreach (AiPromptModuleCatalog::defaults() as $def) {
            $exists = AiPromptModule::query()->where('key', $def['key'])->exists();
            if ($exists) {
                continue;
            }
            AiPromptModule::query()->create([
                'key' => $def['key'],
                'name' => $def['name'],
                'filename' => $def['filename'],
                'description' => $def['description'],
                'kind' => $def['kind'],
                'intent_key' => $def['intent_key'],
                'sort_order' => $def['sort_order'],
                'body' => $def['body'],
                'is_enabled' => true,
                'is_system' => true,
                'updated_by' => null,
            ]);
        }

        // Align meta for system modules. Refresh body only when never admin-edited (updated_by null).
        AiPromptModule::query()
            ->where('is_system', true)
            ->get()
            ->each(function (AiPromptModule $module) use ($defaults): void {
                $def = $defaults->get($module->key);
                if (! is_array($def)) {
                    return;
                }
                $dirty = false;
                if ((string) $module->filename !== (string) $def['filename']) {
                    $module->filename = (string) $def['filename'];
                    $dirty = true;
                }
                if ((string) $module->name !== (string) $def['name']) {
                    $module->name = (string) $def['name'];
                    $dirty = true;
                }
                if ((string) ($module->description ?? '') !== (string) $def['description']) {
                    $module->description = (string) $def['description'];
                    $dirty = true;
                }
                if ((string) $module->kind !== (string) $def['kind']) {
                    $module->kind = (string) $def['kind'];
                    $dirty = true;
                }
                $intent = $def['intent_key'] !== null ? (string) $def['intent_key'] : null;
                if ($module->intent_key !== $intent) {
                    $module->intent_key = $intent;
                    $dirty = true;
                }
                if ((int) $module->sort_order !== (int) $def['sort_order']) {
                    $module->sort_order = (int) $def['sort_order'];
                    $dirty = true;
                }
                if ($module->updated_by === null && (string) $module->body !== (string) $def['body']) {
                    $module->body = (string) $def['body'];
                    $dirty = true;
                }
                if ($dirty) {
                    $module->save();
                }
            });
    }

    /**
     * Enabled module body by key, or catalog default.
     */
    public function bodyForKey(string $key): string
    {
        if ($this->tableReady()) {
            $this->ensureSeeded();
            $module = AiPromptModule::query()
                ->where('key', $key)
                ->where('is_enabled', true)
                ->first();
            if ($module) {
                return trim((string) $module->body);
            }
        }

        return trim((string) (AiPromptModuleCatalog::bodyForKey($key) ?? AiPromptModuleCatalog::coreBody()));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AiPromptModule $module, bool $withBody): array
    {
        $row = [
            'id' => (string) $module->id,
            'key' => $module->key,
            'name' => $module->name,
            'filename' => $module->filename,
            'description' => $module->description,
            'kind' => $module->kind,
            'intent_key' => $module->intent_key,
            'sort_order' => $module->sort_order,
            'is_enabled' => (bool) $module->is_enabled,
            'is_system' => (bool) $module->is_system,
            'updated_at' => optional($module->updated_at)?->toIso8601String(),
            'body_chars' => strlen((string) $module->body),
        ];

        if ($withBody) {
            $row['body'] = (string) $module->body;
        }

        return $row;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('ai_prompt_modules');
        } catch (\Throwable) {
            return false;
        }
    }

    private function fallbackCoreOnly(): string
    {
        return AiPromptModuleCatalog::coreBody();
    }
}
