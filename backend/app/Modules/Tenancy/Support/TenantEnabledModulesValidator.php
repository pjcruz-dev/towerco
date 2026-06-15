<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use Illuminate\Validation\ValidationException;

final class TenantEnabledModulesValidator
{
    /**
     * @param  mixed  $input
     * @return list<string>|null
     */
    public static function validate(mixed $input, TenantEnabledModulesResolver $resolver): ?array
    {
        if ($input === null) {
            return null;
        }

        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'enabled_modules' => [__('Enabled modules must be an array.')],
            ]);
        }

        $modules = array_values(array_unique(array_filter(array_map(
            static fn (mixed $module): string => is_string($module) ? trim($module) : '',
            $input,
        ))));

        $toggleableSelection = array_values(array_diff(
            $modules,
            TenantEnabledModulesResolver::REQUIRED_MODULES,
        ));

        if ($toggleableSelection === []) {
            return $resolver->normalizeSelection([]);
        }

        $platform = $resolver->platformModules();
        $toggleable = $resolver->toggleableModules();
        $invalid = array_values(array_diff($toggleableSelection, $toggleable));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'enabled_modules' => [
                    __('Invalid or non-toggleable modules: :modules', [
                        'modules' => implode(', ', $invalid),
                    ]),
                ],
            ]);
        }

        $notOnPlatform = array_values(array_diff($toggleableSelection, $platform));
        if ($notOnPlatform !== []) {
            throw ValidationException::withMessages([
                'enabled_modules' => [
                    __('Modules not enabled on this deployment: :modules', [
                        'modules' => implode(', ', $notOnPlatform),
                    ]),
                ],
            ]);
        }

        return $resolver->normalizeSelection($toggleableSelection);
    }
}
