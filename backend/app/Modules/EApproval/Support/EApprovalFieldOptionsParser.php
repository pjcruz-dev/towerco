<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalFieldOptionsParser
{
    /**
     * @param  array<string, mixed>|null  $options
     * @return list<array{value: string, label: string}>
     */
    public static function selectChoices(?array $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        $entries = array_is_list($options)
            ? $options
            : (is_array($options['choices'] ?? null) ? $options['choices'] : []);

        $choices = [];
        foreach ($entries as $entry) {
            $parsed = self::parseChoiceEntry($entry);
            if ($parsed !== null) {
                $choices[] = $parsed;
            }
        }

        return $choices;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return list<string>
     */
    public static function gridColumns(?array $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        if (array_is_list($options)) {
            return array_values(array_filter(array_map(static function (mixed $col): string {
                if (is_string($col)) {
                    return trim($col);
                }

                if (is_array($col)) {
                    return trim((string) ($col['label'] ?? $col['name'] ?? ''));
                }

                return '';
            }, $options), static fn (string $col): bool => $col !== ''));
        }

        $columns = $options['columns'] ?? null;
        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $col): string {
            if (is_string($col)) {
                return trim($col);
            }

            if (is_array($col)) {
                $label = trim((string) ($col['label'] ?? $col['name'] ?? ''));

                return $label;
            }

            return '';
        }, $columns), static fn (string $col): bool => $col !== ''));
    }

    public static function choiceLabel(?array $options, ?string $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return $rawValue;
        }

        foreach (self::selectChoices($options) as $choice) {
            if ($choice['value'] === $rawValue) {
                return $choice['label'] !== '' ? $choice['label'] : $rawValue;
            }
        }

        return $rawValue;
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function parseChoiceEntry(mixed $entry): ?array
    {
        if (is_array($entry) && isset($entry['value'])) {
            $value = trim((string) $entry['value']);
            if ($value === '') {
                return null;
            }
            $label = trim((string) ($entry['label'] ?? $value));

            return [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
            ];
        }

        if (! is_string($entry)) {
            return null;
        }

        $text = trim($entry);
        if ($text === '') {
            return null;
        }

        $pipe = strpos($text, '|');
        if ($pipe !== false) {
            $label = trim(substr($text, 0, $pipe));
            $value = trim(substr($text, $pipe + 1));

            return [
                'value' => $value !== '' ? $value : $label,
                'label' => $label !== '' ? $label : $value,
            ];
        }

        return ['value' => $text, 'label' => $text];
    }
}
