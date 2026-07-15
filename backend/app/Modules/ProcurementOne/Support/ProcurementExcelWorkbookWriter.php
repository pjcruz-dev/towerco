<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use ZipArchive;

/**
 * Minimal multi-sheet XLSX writer (Office Open XML) without external dependencies.
 */
final class ProcurementExcelWorkbookWriter
{
    /** @var list<array{name: string, rows: list<list<string|int|float|null>>}> */
    private array $sheets = [];

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function addSheet(string $name, array $rows): void
    {
        $this->sheets[] = [
            'name' => mb_substr($name, 0, 31),
            'rows' => $rows,
        ];
    }

    public function toBinaryString(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'toweros-xlsx-');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temporary XLSX file.');
        }

        $path = $tmp.'.xlsx';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open XLSX archive.');
        }

        $sheetCount = count($this->sheets);
        $contentTypes = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
            '<Default Extension="xml" ContentType="application/xml"/>',
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
        ];

        for ($i = 1; $i <= $sheetCount; $i++) {
            $contentTypes[] = sprintf(
                '<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                $i,
            );
        }

        $contentTypes[] = '</Types>';
        $zip->addFromString('[Content_Types].xml', implode('', $contentTypes));

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $workbookRels = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
        ];
        $workbookSheets = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">',
            '<sheets>',
        ];

        foreach ($this->sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $rid = 'rId'.$sheetNumber;
            $workbookRels[] = sprintf(
                '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>',
                $rid,
                $sheetNumber,
            );
            $escapedName = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $workbookSheets[] = sprintf('<sheet name="%s" sheetId="%d" r:id="%s"/>', $escapedName, $sheetNumber, $rid);
            $zip->addFromString('xl/worksheets/sheet'.$sheetNumber.'.xml', $this->buildWorksheetXml($sheet['rows']));
        }

        $workbookRels[] = sprintf(
            '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
            $sheetCount + 1,
        );
        $workbookRels[] = '</Relationships>';
        $workbookSheets[] = '</sheets></workbook>';

        $zip->addFromString('xl/_rels/workbook.xml.rels', implode('', $workbookRels));
        $zip->addFromString('xl/workbook.xml', implode('', $workbookSheets));
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>');

        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function buildWorksheetXml(array $rows): string
    {
        $parts = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>',
        ];

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $parts[] = '<row r="'.$rowNumber.'">';
            foreach ($row as $colIndex => $value) {
                $cellRef = self::columnLetter($colIndex + 1).$rowNumber;
                $parts[] = $this->buildCellXml($cellRef, $value);
            }
            $parts[] = '</row>';
        }

        $parts[] = '</sheetData></worksheet>';

        return implode('', $parts);
    }

    private function buildCellXml(string $cellRef, string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$cellRef.'"/>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$cellRef.'"><v>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</v></c>';
        }

        $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
    }

    public static function columnLetter(int $columnNumber): string
    {
        $letters = '';
        while ($columnNumber > 0) {
            $mod = ($columnNumber - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $letters;
    }
}
