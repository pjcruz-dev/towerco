<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Identity\Models\TenantUser;

final class EApprovalFieldMapResolver
{
    /**
     * @param  array<string, mixed>  $mappings
     * @param  list<array{value?: string, label?: string}>  $choices
     */
    public function resolveApproverId(
        array $mappings,
        string $raw,
        ?string $defaultApproverId = null,
        array $choices = [],
    ): ?string {
        $raw = trim($raw);

        if ($raw !== '') {
            $mapped = $this->lookupMapping($mappings, $raw, $choices);
            if ($mapped !== null && $mapped !== '') {
                return $this->resolveUserId($mapped);
            }
        }

        return $this->resolveUserId(is_string($defaultApproverId) ? trim($defaultApproverId) : null);
    }

    /**
     * @param  array<string, mixed>  $mappings
     * @param  list<array{value?: string, label?: string}>  $choices
     */
    public function resolveMatchedKey(array $mappings, string $raw, array $choices = []): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (isset($mappings[$raw]) && trim((string) $mappings[$raw]) !== '') {
            return $raw;
        }

        $needle = strtolower($raw);
        foreach ($mappings as $key => $userId) {
            if (trim((string) $userId) === '') {
                continue;
            }

            if (strtolower(trim((string) $key)) === $needle) {
                return (string) $key;
            }
        }

        $canonicalRaw = $this->canonicalizeFieldValue($raw, $choices);
        foreach ($mappings as $key => $userId) {
            if (trim((string) $userId) === '') {
                continue;
            }

            $canonicalKey = $this->canonicalizeFieldValue((string) $key, $choices);
            if ($canonicalKey !== '' && $canonicalKey === $canonicalRaw) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mappings
     * @param  list<array{value?: string, label?: string}>  $choices
     */
    private function lookupMapping(array $mappings, string $raw, array $choices): ?string
    {
        if (isset($mappings[$raw])) {
            return trim((string) $mappings[$raw]);
        }

        $needle = strtolower(trim($raw));
        foreach ($mappings as $key => $userId) {
            if (strtolower(trim((string) $key)) === $needle) {
                return trim((string) $userId);
            }
        }

        if ($choices === []) {
            return null;
        }

        $canonicalRaw = $this->canonicalizeFieldValue($raw, $choices);
        foreach ($mappings as $key => $userId) {
            $canonicalKey = $this->canonicalizeFieldValue((string) $key, $choices);
            if ($canonicalKey !== '' && $canonicalKey === $canonicalRaw) {
                $trimmed = trim((string) $userId);

                return $trimmed !== '' ? $trimmed : null;
            }
        }

        return null;
    }

    /**
     * @param  list<array{value?: string, label?: string}>  $choices
     */
    private function canonicalizeFieldValue(string $raw, array $choices): string
    {
        $raw = trim($raw);
        if ($raw === '' || $choices === []) {
            return $raw;
        }

        $needle = strtolower($raw);
        foreach ($choices as $choice) {
            $value = strtolower(trim((string) ($choice['value'] ?? '')));
            $label = strtolower(trim((string) ($choice['label'] ?? '')));
            if ($needle === $value || $needle === $label) {
                return trim((string) ($choice['value'] ?? $choice['label'] ?? $raw));
            }
        }

        return $raw;
    }

    private function resolveUserId(?string $candidate): ?string
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return null;
        }

        $userId = TenantUser::query()
            ->where('id', $candidate)
            ->where('is_active', true)
            ->value('id');

        if ($userId !== null) {
            return (string) $userId;
        }

        if (str_contains($candidate, '@')) {
            $byEmail = TenantUser::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($candidate)])
                ->where('is_active', true)
                ->value('id');

            return $byEmail !== null ? (string) $byEmail : null;
        }

        return null;
    }
}
