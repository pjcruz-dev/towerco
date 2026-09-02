<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Metacoresoft-style BIR PDF overlay catalog.
 * Bundled assets ship with the frontend; tenant uploads fill the rest.
 */
final class DynPdfFormCatalog
{
    /**
     * @return list<array{code: string, name: string, path: string, bundled: bool, preview_path: string|null}>
     */
    public static function entries(): array
    {
        return [
            [
                'code' => '2307',
                'name' => 'BIR Form 2307 (Jan 2018)',
                'path' => 'forms/bir-2307-jan-2018-encs-v3.pdf',
                'bundled' => true,
                'preview_path' => '/forms/bir-2307-jan-2018-encs-v3.pdf',
            ],
            ['code' => '0217', 'name' => 'BIR Form 0217', 'path' => 'bir-forms/0217.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '0605', 'name' => 'BIR Form 0605', 'path' => 'bir-forms/0605.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '0611-A', 'name' => 'BIR Form 0611-A', 'path' => 'bir-forms/0611-A.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '0613-A', 'name' => 'BIR Form 0613-A', 'path' => 'bir-forms/0613-A.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '0619-E', 'name' => 'BIR Form 0619-E', 'path' => 'bir-forms/0619-E.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '0619-F', 'name' => 'BIR Form 0619-F', 'path' => 'bir-forms/0619-F.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1601-C', 'name' => 'BIR Form 1601-C', 'path' => 'bir-forms/1601-C.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1601-EQ', 'name' => 'BIR Form 1601-EQ', 'path' => 'bir-forms/1601-EQ.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1601-FQ', 'name' => 'BIR Form 1601-FQ', 'path' => 'bir-forms/1601-FQ.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1604-C', 'name' => 'BIR Form 1604-C', 'path' => 'bir-forms/1604-C.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1604-E', 'name' => 'BIR Form 1604-E', 'path' => 'bir-forms/1604-E.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1700', 'name' => 'BIR Form 1700', 'path' => 'bir-forms/1700.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1701', 'name' => 'BIR Form 1701', 'path' => 'bir-forms/1701.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '1702-RT', 'name' => 'BIR Form 1702-RT', 'path' => 'bir-forms/1702-RT.pdf', 'bundled' => false, 'preview_path' => null],
            ['code' => '2550-Q', 'name' => 'BIR Form 2550-Q', 'path' => 'bir-forms/2550-Q.pdf', 'bundled' => false, 'preview_path' => null],
        ];
    }

    /**
     * @return array{code: string, name: string, path: string, bundled: bool, preview_path: string|null}|null
     */
    public static function find(string $code): ?array
    {
        $normalized = self::normalizeCode($code);
        foreach (self::entries() as $entry) {
            if (strcasecmp($entry['code'], $normalized) === 0) {
                return $entry;
            }
        }

        return null;
    }

    public static function normalizeCode(string $code): string
    {
        $code = trim($code);
        $code = preg_replace('/\.pdf$/i', '', $code) ?? $code;

        return strtoupper(preg_replace('/\s+/', '-', $code) ?? $code);
    }
}
