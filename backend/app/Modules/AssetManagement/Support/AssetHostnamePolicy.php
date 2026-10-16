<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Support;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class AssetHostnamePolicy
{
    public const MAX_LENGTH = 15;

    public static function suggest(TenantUser $user, string $departmentCode): string
    {
        $code = self::normalizeCode($departmentCode);
        $given = self::letters((string) ($user->given_name ?? ''));
        $surname = self::letters((string) ($user->surname ?? ''));

        if ($given === '' || $surname === '') {
            [$given, $surname] = self::splitDisplayName((string) $user->name, $given, $surname);
        }

        if ($given === '' || $surname === '') {
            throw ValidationException::withMessages([
                'hostname' => [__('This employee needs a first name and a last name before a computer name can be generated. Update the directory name or type a hostname.')],
            ]);
        }

        $first = $given[0];
        $middle = self::middleInitial((string) $user->name, (string) ($user->given_name ?? ''), (string) ($user->surname ?? ''));
        $fixedWithoutMiddle = strlen($first.$code);
        if ($fixedWithoutMiddle > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'hostname' => [__('The department code is too long to fit a 15-character computer name. Shorten the department code, then try again.')],
            ]);
        }

        $prefix = $first.$middle;
        if (strlen($prefix.$code) > self::MAX_LENGTH) {
            $prefix = $first;
        }

        $room = self::MAX_LENGTH - strlen($prefix.$code);
        if ($room < 1) {
            throw ValidationException::withMessages([
                'hostname' => [__('The department code leaves no room for the employee name. Shorten the department code, then try again.')],
            ]);
        }

        return strtoupper($prefix.substr($surname, 0, $room).$code);
    }

    public static function assertLength(string $hostname): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9-]/', '', strtoupper(trim($hostname))) ?? '');
        if ($normalized === '' || strlen($normalized) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'hostname' => [__('Computer names must be 1 to 15 letters or numbers.')],
            ]);
        }

        return $normalized;
    }

    public static function normalizeCode(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code))) ?? '');
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'department_code' => [__('Add a department code before generating a computer name.')],
            ]);
        }

        return $normalized;
    }

    private static function letters(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitDisplayName(string $displayName, string $given, string $surname): array
    {
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        if ($given === '' && isset($parts[0])) {
            $given = self::letters($parts[0]);
        }
        if ($surname === '' && count($parts) >= 2) {
            $surname = self::letters($parts[count($parts) - 1]);
        }

        return [$given, $surname];
    }

    private static function middleInitial(string $displayName, string $givenName, string $surname): string
    {
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        if (count($parts) < 3) {
            return '';
        }

        $first = self::letters($givenName !== '' ? $givenName : $parts[0]);
        $last = self::letters($surname !== '' ? $surname : $parts[count($parts) - 1]);
        $middleParts = array_slice($parts, 1, -1);
        if ($middleParts === []) {
            return '';
        }

        $middle = self::letters($middleParts[0]);
        if ($middle === '' || $middle === $first || $middle === $last) {
            return '';
        }

        return $middle[0];
    }
}
