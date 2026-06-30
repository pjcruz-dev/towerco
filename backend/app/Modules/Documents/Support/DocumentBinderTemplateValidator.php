<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use Illuminate\Validation\ValidationException;

final class DocumentBinderTemplateValidator
{
    /** @var list<string> */
    private const ALLOWED_TYPES = ['binder', 'folder', 'fixed', 'repeatable_container'];

    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'esite_binder',
        'saq_phase_1',
        'lessors',
        'legal',
        'col',
        'affidavit',
        'esite_folder',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function validate(mixed $tree): array
    {
        if (! is_array($tree) || $tree === []) {
            throw ValidationException::withMessages([
                'tree' => [__('Binder template must be a non-empty tree.')],
            ]);
        }

        $keys = [];
        $this->walk($tree, $keys);

        foreach (self::REQUIRED_KEYS as $requiredKey) {
            if (! in_array($requiredKey, $keys, true)) {
                throw ValidationException::withMessages([
                    'tree' => [__('Binder template is missing required folder: :key.', ['key' => $requiredKey])],
                ]);
            }
        }

        /** @var list<array<string, mixed>> $tree */
        return $tree;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  list<string>  $keys
     */
    private function walk(array $nodes, array &$keys, string $path = 'tree'): void
    {
        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}" => [__('Each binder node must be an object.')],
                ]);
            }

            $key = (string) ($node['key'] ?? '');
            $label = trim((string) ($node['label'] ?? ''));
            $type = (string) ($node['type'] ?? '');

            if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}.key" => [__('Node key must be lowercase snake_case.')],
                ]);
            }

            if ($label === '') {
                throw ValidationException::withMessages([
                    "{$path}.{$index}.label" => [__('Node label is required.')],
                ]);
            }

            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}.type" => [__('Invalid binder node type.')],
                ]);
            }

            if (in_array($key, $keys, true)) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}.key" => [__('Duplicate binder node key: :key.', ['key' => $key])],
                ]);
            }

            $keys[] = $key;

            $children = $node['children'] ?? [];
            if ($children !== [] && ! is_array($children)) {
                throw ValidationException::withMessages([
                    "{$path}.{$index}.children" => [__('Children must be an array.')],
                ]);
            }

            if (is_array($children) && $children !== []) {
                $this->walk($children, $keys, "{$path}.{$index}.children");
            }
        }
    }
}
