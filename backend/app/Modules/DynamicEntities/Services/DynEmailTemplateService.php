<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEmailTemplate;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynEmailTemplateService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return DynEmailTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DynEmailTemplate $t): array => $this->present($t, false))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(DynEmailTemplate $template): array
    {
        return $this->present($template, true);
    }

    /**
     * @param  array{
     *   name: string,
     *   slug?: string|null,
     *   description?: string|null,
     *   entity_slug?: string|null,
     *   subject: string,
     *   body_html: string,
     *   body_text?: string|null,
     *   default_to?: string|null,
     *   cc?: string|null,
     *   bcc?: string|null,
     *   is_active?: bool|null
     * }  $data
     * @return array<string, mixed>
     */
    public function create(array $data, TenantUser $actor): array
    {
        $name = trim((string) $data['name']);
        $slug = $this->normalizeSlug((string) ($data['slug'] ?? ''), $name);
        $this->assertUniqueSlug($slug);

        $subject = trim((string) $data['subject']);
        if ($subject === '') {
            throw ValidationException::withMessages(['subject' => [__('Email subject is required.')]]);
        }

        $template = DynEmailTemplate::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullableTrim($data['description'] ?? null),
            'entity_slug' => $this->nullableTrim($data['entity_slug'] ?? null),
            'subject' => $subject,
            'body_html' => (string) ($data['body_html'] ?? ''),
            'body_text' => $this->nullableTrim($data['body_text'] ?? null),
            'default_to' => $this->nullableTrim($data['default_to'] ?? null),
            'cc' => $this->nullableTrim($data['cc'] ?? null),
            'bcc' => $this->nullableTrim($data['bcc'] ?? null),
            'is_system' => false,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($template, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(DynEmailTemplate $template, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $template->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data)) {
            $slug = $this->normalizeSlug((string) ($data['slug'] ?? ''), (string) $template->name);
            $this->assertUniqueSlug($slug, (string) $template->id);
            $template->slug = $slug;
        }
        if (array_key_exists('description', $data)) {
            $template->description = $this->nullableTrim($data['description'] ?? null);
        }
        if (array_key_exists('entity_slug', $data)) {
            $template->entity_slug = $this->nullableTrim($data['entity_slug'] ?? null);
        }
        if (array_key_exists('subject', $data)) {
            $subject = trim((string) $data['subject']);
            if ($subject === '') {
                throw ValidationException::withMessages(['subject' => [__('Email subject is required.')]]);
            }
            $template->subject = $subject;
        }
        if (array_key_exists('body_html', $data)) {
            $template->body_html = (string) $data['body_html'];
        }
        if (array_key_exists('body_text', $data)) {
            $template->body_text = $this->nullableTrim($data['body_text'] ?? null);
        }
        if (array_key_exists('default_to', $data)) {
            $template->default_to = $this->nullableTrim($data['default_to'] ?? null);
        }
        if (array_key_exists('cc', $data)) {
            $template->cc = $this->nullableTrim($data['cc'] ?? null);
        }
        if (array_key_exists('bcc', $data)) {
            $template->bcc = $this->nullableTrim($data['bcc'] ?? null);
        }
        if (array_key_exists('is_active', $data)) {
            $template->is_active = (bool) $data['is_active'];
        }

        $template->updated_by = (string) $actor->id;
        $template->save();

        return $this->present($template, true);
    }

    public function destroy(DynEmailTemplate $template): void
    {
        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => [__('System email templates cannot be deleted.')],
            ]);
        }

        $template->delete();
    }

    public function findBySlug(string $slug): ?DynEmailTemplate
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return DynEmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Merge placeholders for workflow / preview use.
     *
     * Supported tokens:
     * - {this.field} / {self.field} (record; {self.email} stays actor for workflow To compat)
     * - {alias.field} related bags
     * - {actor.email} / {actor.name}
     * - {{system.today}} / {{system.now}} / {{record.field}}
     *
     * @param  array<string, array{values?: array<string, mixed>, status?: ?string, title?: ?string, email?: ?string}>  $context
     */
    public function merge(string $text, array $context, ?TenantUser $actor = null): string
    {
        $actorEmail = (string) ($actor?->email ?? '');
        $actorName = trim((string) ($actor?->name ?? ''));

        $out = strtr($text, [
            '{actor.email}' => $actorEmail,
            '{actor.name}' => $actorName,
            '{self.email}' => $actorEmail,
            '{{system.today}}' => now()->toDateString(),
            '{{system.now}}' => now()->toIso8601String(),
            '{{current_date}}' => now()->toDateString(),
        ]);

        $out = (string) preg_replace_callback(
            '/\{\{\s*record\.([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $m) use ($context): string {
                $field = $m[1];
                $bag = $context['this'] ?? [];
                if ($field === 'title') {
                    return (string) ($bag['title'] ?? '');
                }
                if ($field === 'status') {
                    return (string) ($bag['status'] ?? '');
                }
                $values = is_array($bag['values'] ?? null) ? $bag['values'] : [];
                $raw = $values[$field] ?? null;

                return is_scalar($raw) ? (string) $raw : '';
            },
            $out,
        );

        $out = (string) preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/',
            function (array $m) use ($context, $actorEmail, $actorName): string {
                $alias = $m[1];
                $field = $m[2];

                if ($alias === 'actor') {
                    return match ($field) {
                        'email' => $actorEmail,
                        'name' => $actorName,
                        default => '',
                    };
                }

                // Workflow To compat: {self.email} = acting user.
                if ($alias === 'self' && $field === 'email') {
                    return $actorEmail;
                }

                $resolvedAlias = $alias === 'self' ? 'this' : $alias;
                $bag = $context[$resolvedAlias] ?? null;
                if (! is_array($bag)) {
                    return '';
                }
                if ($field === 'title') {
                    return (string) ($bag['title'] ?? '');
                }
                if ($field === 'status') {
                    return (string) ($bag['status'] ?? '');
                }
                if ($field === 'email' && ! empty($bag['email'])) {
                    return (string) $bag['email'];
                }
                $values = is_array($bag['values'] ?? null) ? $bag['values'] : [];
                $raw = $values[$field] ?? null;

                return is_scalar($raw) ? (string) $raw : '';
            },
            $out,
        );

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DynEmailTemplate $template, bool $withBody): array
    {
        $payload = [
            'id' => (string) $template->id,
            'name' => (string) $template->name,
            'slug' => (string) $template->slug,
            'description' => $template->description,
            'entity_slug' => $template->entity_slug,
            'subject' => (string) $template->subject,
            'default_to' => $template->default_to,
            'cc' => $template->cc,
            'bcc' => $template->bcc,
            'is_system' => (bool) $template->is_system,
            'is_active' => (bool) $template->is_active,
            'created_at' => optional($template->created_at)?->toIso8601String(),
            'updated_at' => optional($template->updated_at)?->toIso8601String(),
        ];

        if ($withBody) {
            $payload['body_html'] = (string) ($template->body_html ?? '');
            $payload['body_text'] = $template->body_text;
        }

        return $payload;
    }

    private function normalizeSlug(string $slug, string $fallbackName): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = $fallbackName;
        }
        $slug = Str::slug($slug);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => [__('A valid slug is required.')],
            ]);
        }
        if (strlen($slug) > 160) {
            $slug = substr($slug, 0, 160);
        }

        return $slug;
    }

    private function assertUniqueSlug(string $slug, ?string $exceptId = null): void
    {
        $q = DynEmailTemplate::query()->where('slug', $slug);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages([
                'slug' => [__('That email template slug is already in use.')],
            ]);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
