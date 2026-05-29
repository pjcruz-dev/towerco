<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use Illuminate\Validation\ValidationException;

final class RolloutPolicyBundleValidator
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $timelineTemplates
     * @param  array<string, array{working_days?: int}>  $deliveryPeriods
     */
    public function validate(array $timelineTemplates, array $deliveryPeriods): void
    {
        $errors = [];

        foreach ($timelineTemplates as $templateKey => $phases) {
            if (! is_array($phases)) {
                $errors["timeline_templates.{$templateKey}"] = [__('Invalid timeline template.')];

                continue;
            }

            $seenKeys = [];
            $postDayOneTotal = 0;

            foreach ($phases as $index => $phase) {
                $phaseKey = (string) ($phase['phase_key'] ?? '');
                if ($phaseKey === '') {
                    $errors["timeline_templates.{$templateKey}.{$index}.phase_key"] = [__('Phase key is required.')];
                } elseif (isset($seenKeys[$phaseKey])) {
                    $errors["timeline_templates.{$templateKey}.{$index}.phase_key"] = [__('Duplicate phase key.')];
                } else {
                    $seenKeys[$phaseKey] = true;
                }

                if (($phase['anchor'] ?? '') === 'tssr_approved' && $this->countsTowardSla($phase)) {
                    $start = (int) ($phase['working_day_start'] ?? 0);
                    $end = (int) ($phase['working_day_end'] ?? 0);
                    $postDayOneTotal += max(0, $end - $start + 1);
                }
            }

            $sla = (int) ($deliveryPeriods[$templateKey]['working_days'] ?? 0);
            if ($sla > 0 && $postDayOneTotal > 0 && $postDayOneTotal !== $sla) {
                $errors["timeline_templates.{$templateKey}"] = [
                    __('Post–Day-1 phases must total :sla working days (currently :total).', [
                        'sla' => $sla,
                        'total' => $postDayOneTotal,
                    ]),
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function countsTowardSla(array $phase): bool
    {
        if (! array_key_exists('counts_toward_sla', $phase)) {
            return true;
        }

        return (bool) $phase['counts_toward_sla'];
    }
}
