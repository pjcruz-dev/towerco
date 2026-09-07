<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-wide subsidiary letterhead logos (ATC/ADIC/…).
 * Forms inherit these for {{system.subsidiary_logo}}; per-form paths remain as overrides.
 */
final class EApprovalSubsidiaryLogoCatalogService
{
    public const SETTINGS_KEY = 'subsidiary_logo_catalog';

    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    private function pdfLayout(): EApprovalPdfLayoutService
    {
        return app(EApprovalPdfLayoutService::class);
    }

    /**
     * @return array{codes: list<string>, logos: array<string, string>}
     */
    public function raw(): array
    {
        $this->ensureSeeded();
        $stored = $this->settings->getJson(self::SETTINGS_KEY, []);
        $codes = $this->pdfLayout()->sanitizeSubsidiaryCodes(
            is_array($stored['codes'] ?? null) ? $stored['codes'] : [],
        );
        $logos = $this->sanitizeLogoPaths(
            is_array($stored['logos'] ?? null) ? $stored['logos'] : [],
        );

        if ($codes === []) {
            $codes = $this->pdfLayout()->defaultSubsidiaryCodes();
        }

        foreach (array_keys($logos) as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return [
            'codes' => array_values($codes),
            'logos' => $logos,
        ];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->raw()['codes'];
    }

    /**
     * Storage paths keyed by code.
     *
     * @return array<string, string>
     */
    public function logoPaths(): array
    {
        return $this->raw()['logos'];
    }

    public function logoPath(string $code): ?string
    {
        try {
            $code = $this->pdfLayout()->normalizeSubsidiaryCode($code);
        } catch (ValidationException) {
            return null;
        }

        $path = trim((string) ($this->logoPaths()[$code] ?? ''));

        return $path !== '' ? $path : null;
    }

    /**
     * Presented API payload for Settings UI / consumers.
     *
     * @return array{codes: list<string>, logos: array<string, string>}
     */
    public function present(): array
    {
        $raw = $this->raw();
        $presented = [];
        foreach ($raw['codes'] as $code) {
            if (isset($raw['logos'][$code]) && $raw['logos'][$code] !== '') {
                $presented[$code] = '/api/v1/e-approval/subsidiary-logos/'.$code;
            }
        }

        return [
            'codes' => $raw['codes'],
            'logos' => $presented,
        ];
    }

    /**
     * @return list<string>
     */
    public function registerCode(string $code): array
    {
        $code = $this->pdfLayout()->normalizeSubsidiaryCode($code);
        $raw = $this->raw();
        if (! in_array($code, $raw['codes'], true)) {
            $raw['codes'][] = $code;
        }
        $this->persist($raw);

        return $raw['codes'];
    }

    public function setLogoPath(string $code, string $storagePath): void
    {
        $code = $this->pdfLayout()->normalizeSubsidiaryCode($code);
        $raw = $this->raw();
        if (! in_array($code, $raw['codes'], true)) {
            $raw['codes'][] = $code;
        }
        $raw['logos'][$code] = $storagePath;
        $this->persist($raw);
    }

    /**
     * @return list<string>
     */
    public function removeCode(string $code, bool $deleteFile = true): array
    {
        $code = $this->pdfLayout()->normalizeSubsidiaryCode($code);
        $raw = $this->raw();
        $previous = $raw['logos'][$code] ?? null;
        $raw['codes'] = array_values(array_filter(
            $raw['codes'],
            static fn (string $c): bool => $c !== $code,
        ));
        unset($raw['logos'][$code]);
        if ($raw['codes'] === []) {
            $raw['codes'] = $this->pdfLayout()->defaultSubsidiaryCodes();
        }
        $this->persist($raw);

        if ($deleteFile && is_string($previous) && $previous !== '' && str_contains($previous, '/e-approval/subsidiaries/')) {
            app(EApprovalFileStorageService::class)->deleteIfExists($previous);
        }

        return $raw['codes'];
    }

    public function clearLogo(string $code, bool $deleteFile = true): void
    {
        $code = $this->pdfLayout()->normalizeSubsidiaryCode($code);
        $raw = $this->raw();
        $previous = $raw['logos'][$code] ?? null;
        unset($raw['logos'][$code]);
        $this->persist($raw);

        if ($deleteFile && is_string($previous) && $previous !== '' && str_contains($previous, '/e-approval/subsidiaries/')) {
            app(EApprovalFileStorageService::class)->deleteIfExists($previous);
        }
    }

    /**
     * Seed ATC/ADIC and copy any existing per-form logos into the catalog once.
     */
    public function ensureSeeded(): void
    {
        $stored = $this->settings->getJson(self::SETTINGS_KEY);
        if (is_array($stored) && (
            (isset($stored['codes']) && is_array($stored['codes']) && $stored['codes'] !== [])
            || (isset($stored['logos']) && is_array($stored['logos']) && $stored['logos'] !== [])
        )) {
            return;
        }

        $codes = $this->pdfLayout()->defaultSubsidiaryCodes();
        $logos = [];

        $rows = DB::connection('tenant')
            ->table('e_approval_settings')
            ->where('key', 'like', 'pdf_layout_form_%')
            ->get(['value']);

        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row->value ?? ''), true);
            if (! is_array($decoded)) {
                continue;
            }
            $template = is_array($decoded['template'] ?? null) ? $decoded['template'] : [];
            $formLogos = $this->pdfLayout()->sanitizeSubsidiaryLogos(
                is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [],
            );
            foreach ($formLogos as $code => $path) {
                if (! isset($logos[$code]) && $path !== '' && ! str_starts_with($path, '/api/')) {
                    $logos[$code] = $path;
                    if (! in_array($code, $codes, true)) {
                        $codes[] = $code;
                    }
                }
            }
            $formCodes = $this->pdfLayout()->sanitizeSubsidiaryCodes(
                is_array($template['subsidiary_codes'] ?? null) ? $template['subsidiary_codes'] : [],
            );
            foreach ($formCodes as $code) {
                if (! in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        }

        $this->persist([
            'codes' => $codes !== [] ? $codes : $this->pdfLayout()->defaultSubsidiaryCodes(),
            'logos' => $logos,
        ]);
    }

    /**
     * @param  array{codes: list<string>, logos: array<string, string>}  $raw
     */
    private function persist(array $raw): void
    {
        $this->settings->setJson(self::SETTINGS_KEY, [
            'codes' => $this->pdfLayout()->sanitizeSubsidiaryCodes($raw['codes'] ?? []),
            'logos' => $this->sanitizeLogoPaths($raw['logos'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $logos
     * @return array<string, string>
     */
    private function sanitizeLogoPaths(array $logos): array
    {
        $out = [];
        foreach ($logos as $rawCode => $rawValue) {
            try {
                $code = $this->pdfLayout()->normalizeSubsidiaryCode((string) $rawCode);
            } catch (ValidationException) {
                continue;
            }
            if (! is_string($rawValue)) {
                continue;
            }
            $value = trim($rawValue);
            if ($value === '' || strlen($value) > 512 || str_contains($value, '..')) {
                continue;
            }
            // Storage paths only (presentation URLs are never persisted).
            if (str_starts_with($value, '/api/')) {
                continue;
            }
            if (
                ! str_contains($value, '/e-approval/')
                && ! str_starts_with($value, '/storage/')
            ) {
                continue;
            }
            $out[$code] = $value;
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }
}
