<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Support;

/**
 * Builds a normalized audit "changes" payload:
 *   field => ['from' => mixed, 'to' => mixed]
 *
 * Only business-meaningful scalar / short values are kept. Secrets and bulky
 * payloads are stripped so the governance trail stays operational.
 */
final class WorkspaceAuditChanges
{
    private const MAX_STRING = 240;

    private const SECRET_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'recovery_code',
        'signature',
    ];

    /**
     * @param  array<string, array{from?: mixed, to?: mixed}|mixed>  $changes
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function of(array $changes): array
    {
        $normalized = [];

        foreach ($changes as $field => $value) {
            $key = trim((string) $field);
            if ($key === '' || self::isSecretField($key)) {
                continue;
            }

            if (! is_array($value) || ! array_key_exists('from', $value) || ! array_key_exists('to', $value)) {
                continue;
            }

            $from = self::sanitizeValue($value['from']);
            $to = self::sanitizeValue($value['to']);

            if (self::valuesEqual($from, $to)) {
                continue;
            }

            $normalized[$key] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Diff allowlisted keys between two attribute maps.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after, array $fields): array
    {
        $raw = [];

        foreach ($fields as $field) {
            $key = trim($field);
            if ($key === '') {
                continue;
            }

            $raw[$key] = [
                'from' => $before[$key] ?? null,
                'to' => $after[$key] ?? null,
            ];
        }

        return self::of($raw);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, array{from: mixed, to: mixed}>|null
     */
    public static function extractFromMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $raw = $metadata['changes'] ?? null;
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $normalized = self::of($raw);

        return $normalized === [] ? null : $normalized;
    }

    private static function isSecretField(string $field): bool
    {
        $lower = strtolower($field);
        foreach (self::SECRET_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (mb_strlen($trimmed) > self::MAX_STRING) {
                return mb_substr($trimmed, 0, self::MAX_STRING - 1).'…';
            }

            return $trimmed;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $scalars = [];
                foreach (array_slice($value, 0, 20) as $item) {
                    if (is_scalar($item) || $item === null) {
                        $scalars[] = $item;
                    }
                }

                return $scalars;
            }

            // Associative / nested payloads stay out of the change list.
            return '[object]';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? self::sanitizeValue((string) $value)
                : '[object]';
        }

        return null;
    }

    private static function valuesEqual(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return $left === $right;
        }

        return (string) json_encode($left) === (string) json_encode($right);
    }
}
