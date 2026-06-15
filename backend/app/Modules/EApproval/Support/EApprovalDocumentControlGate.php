<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Document control gate parsed from form metadata_json.documentControlGate.
 */
final class EApprovalDocumentControlGate
{
    public function __construct(
        public readonly int $afterStepOrder,
        public readonly string $documentTitleField,
        public readonly string $previousRevisionField,
        public readonly string $currentRevisionField,
        public readonly string $detailsField,
        public readonly string $reasonField,
        public readonly ?string $documentTitleSourceField = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function parse(?array $metadata): ?self
    {
        if ($metadata === null) {
            return null;
        }

        $raw = $metadata['documentControlGate'] ?? $metadata['document_control_gate'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        $after = (int) ($raw['afterStepOrder'] ?? $raw['after_step_order'] ?? 0);
        if ($after < 1) {
            return null;
        }

        $defaults = [
            'documentTitleField' => 'document_title',
            'previousRevisionField' => 'previous_revision',
            'currentRevisionField' => 'current_revision',
            'detailsField' => 'details',
            'reasonField' => 'reason',
        ];

        return new self(
            afterStepOrder: $after,
            documentTitleField: self::fieldName($raw, 'documentTitleField', $defaults['documentTitleField']),
            previousRevisionField: self::fieldName($raw, 'previousRevisionField', $defaults['previousRevisionField']),
            currentRevisionField: self::fieldName($raw, 'currentRevisionField', $defaults['currentRevisionField']),
            detailsField: self::fieldName($raw, 'detailsField', $defaults['detailsField']),
            reasonField: self::fieldName($raw, 'reasonField', $defaults['reasonField']),
            documentTitleSourceField: self::optionalFieldName($raw, 'documentTitleSourceField'),
        );
    }

    public static function bumpRevisionCode(string $previous): string
    {
        $s = trim($previous);
        if ($s === '') {
            return '';
        }

        if (preg_match('/^(.*-)(\d+)$/', $s, $m)) {
            $n = (int) $m[2] + 1;
            $width = strlen($m[2]);

            return $m[1].str_pad((string) $n, $width, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^(.*?)(\d+)$/', $s, $m)) {
            $n = (int) $m[2] + 1;
            $width = strlen($m[2]);

            return $m[1].str_pad((string) $n, $width, '0', STR_PAD_LEFT);
        }

        return $s.'-002';
    }

    /**
     * @param  array<string, string>  $values
     */
    public function resolvePreviousRevisionLabel(array $values, ?string $documentNo): string
    {
        $dn = trim((string) $documentNo);
        if ($dn !== '') {
            return $dn;
        }

        $prev = trim($values[$this->previousRevisionField] ?? '');
        if ($prev !== '') {
            return $prev;
        }

        return trim($values[$this->currentRevisionField] ?? '');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function fieldName(array $raw, string $camel, string $default): string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $camel) ?? $camel);
        $value = $raw[$camel] ?? $raw[$snake] ?? $default;

        return trim((string) $value) !== '' ? trim((string) $value) : $default;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function optionalFieldName(array $raw, string $camel): ?string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $camel) ?? $camel);
        $value = trim((string) ($raw[$camel] ?? $raw[$snake] ?? ''));

        return $value !== '' ? $value : null;
    }
}
