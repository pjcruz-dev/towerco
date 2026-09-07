<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Support\DynFieldType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Metacoresoft-style Automatic ID: options_json { id_prefix, id_format }.
 * Format tokens: YYYY / YY / Y (year), MM, DD, and #… for zero-padded sequence.
 */
final class DynAutomaticIdService
{
    /**
     * Fill missing automatic_id values on create (ignores client-supplied values).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function assignOnCreate(DynEntity $entity, array $values, ?CarbonInterface $now = null): array
    {
        $entity->loadMissing('fields');
        $now ??= now();

        foreach ($entity->fields as $field) {
            if ($field->type !== DynFieldType::AUTOMATIC_ID || $field->is_virtual) {
                continue;
            }
            $values[(string) $field->name] = $this->nextValue($entity, $field, $now);
        }

        return $values;
    }

    /**
     * Strip automatic_id keys from an update patch so clients cannot overwrite them.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function stripFromUpdate(DynEntity $entity, array $values): array
    {
        $entity->loadMissing('fields');
        foreach ($entity->fields as $field) {
            if ($field->type !== DynFieldType::AUTOMATIC_ID) {
                continue;
            }
            unset($values[(string) $field->name]);
        }

        return $values;
    }

    public function nextValue(DynEntity $entity, DynField $field, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        $options = is_array($field->options_json) ? $field->options_json : [];
        $prefix = (string) ($options['id_prefix'] ?? $options['prefix'] ?? '');
        $format = trim((string) ($options['id_format'] ?? $options['format'] ?? '####'));
        if ($format === '') {
            $format = '####';
        }

        $periodKey = $this->periodKeyFromFormat($format, $now);
        $seq = $this->allocateSequence((string) $entity->id, (string) $field->name, $periodKey);
        $formatted = $this->applyFormat($format, $seq, $now);

        return $prefix.$formatted;
    }

    public function periodKeyFromFormat(string $format, CarbonInterface $now): string
    {
        $hasYear = (bool) preg_match('/Y{1,4}/', $format);
        $hasMonth = str_contains($format, 'MM');
        $hasDay = str_contains($format, 'DD');

        if (! $hasYear && ! $hasMonth && ! $hasDay) {
            return '';
        }

        $parts = [];
        if ($hasYear) {
            $parts[] = $now->format('Y');
        }
        if ($hasMonth) {
            $parts[] = $now->format('m');
        }
        if ($hasDay) {
            $parts[] = $now->format('d');
        }

        return implode('-', $parts);
    }

    public function applyFormat(string $format, int $sequence, CarbonInterface $now): string
    {
        $out = $format;
        // Longest tokens first so YYYY wins over YY / Y.
        $out = str_replace('YYYY', $now->format('Y'), $out);
        $out = str_replace('YY', $now->format('y'), $out);
        $out = preg_replace('/(?<!Y)Y(?!Y)/', $now->format('Y'), $out) ?? $out;
        $out = str_replace('MM', $now->format('m'), $out);
        $out = str_replace('DD', $now->format('d'), $out);

        if (preg_match('/#+/', $out, $matches) === 1) {
            $hashRun = $matches[0];
            $padding = max(1, strlen($hashRun));
            $out = preg_replace('/#+/', str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT), $out, 1) ?? $out;
        } else {
            $out .= (string) $sequence;
        }

        return $out;
    }

    private function allocateSequence(string $entityId, string $fieldName, string $periodKey): int
    {
        $run = function () use ($entityId, $fieldName, $periodKey): int {
            $row = DB::connection('tenant')->table('dyn_field_sequences')
                ->where('entity_id', $entityId)
                ->where('field_name', $fieldName)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            $n = $row ? max(1, (int) $row->next_no) : 1;

            if ($row) {
                DB::connection('tenant')->table('dyn_field_sequences')
                    ->where('entity_id', $entityId)
                    ->where('field_name', $fieldName)
                    ->where('period_key', $periodKey)
                    ->update(['next_no' => $n + 1]);
            } else {
                DB::connection('tenant')->table('dyn_field_sequences')->insert([
                    'entity_id' => $entityId,
                    'field_name' => $fieldName,
                    'period_key' => $periodKey,
                    'next_no' => $n + 1,
                ]);
            }

            return $n;
        };

        if (DB::connection('tenant')->transactionLevel() > 0) {
            return $run();
        }

        return (int) DB::connection('tenant')->transaction($run);
    }
}
