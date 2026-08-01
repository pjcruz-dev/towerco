<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx writer.
 *
 * Produces a single-sheet workbook with a bold, frozen header row using inline
 * strings (no shared-strings table). Numeric-looking values are written as
 * numbers so Excel can aggregate them; everything else stays as text to avoid
 * mangling document numbers, UUIDs, or values with leading zeros.
 */
final class SimpleXlsxWriter
{
    /**
     * Build a single-sheet .xlsx file at a temporary path and return the path.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<string>>  $rows
     */
    public function write(string $sheetName, array $headers, iterable $rows): string
    {
        return $this->writeSheets([
            ['name' => $sheetName, 'headers' => $headers, 'rows' => $rows],
        ]);
    }

    /**
     * Build a multi-sheet .xlsx file at a temporary path and return the path.
     *
     * @param  list<array{name: string, headers: list<string>, rows: iterable<int, list<string>>}>  $sheets
     */
    public function writeSheets(array $sheets): string
    {
        if ($sheets === []) {
            $sheets = [['name' => 'Sheet1', 'headers' => [], 'rows' => []]];
        }

        $path = tempnam(sys_get_temp_dir(), 'eapproval-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the XLSX export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the XLSX archive for writing.');
        }

        $count = count($sheets);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml($count));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml($count));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                $this->sheetXml($sheet['headers'] ?? [], $sheet['rows'] ?? []),
            );
        }

        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize the XLSX archive.');
        }

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string>>  $rows
     */
    private function sheetXml(array $headers, iterable $rows): string
    {
        $columnCount = max(count($headers), 1);
        $lastCol = $this->columnLetter($columnCount - 1);

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheet .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
        $sheet .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
        $sheet .= '</sheetView></sheetViews>';
        $sheet .= '<sheetFormatPr defaultRowHeight="15"/>';
        $sheet .= '<sheetData>';

        $rowIndex = 1;
        $sheet .= $this->rowXml($rowIndex, $headers, true);

        foreach ($rows as $cells) {
            $rowIndex++;
            $sheet .= $this->rowXml($rowIndex, $cells, false);
        }

        $sheet .= '</sheetData>';
        $sheet .= '<autoFilter ref="A1:'.$lastCol.'1"/>';
        $sheet .= '</worksheet>';

        return $sheet;
    }

    /**
     * @param  list<string>  $cells
     */
    private function rowXml(int $rowIndex, array $cells, bool $isHeader): string
    {
        $xml = '<row r="'.$rowIndex.'">';

        foreach (array_values($cells) as $columnIndex => $value) {
            $ref = $this->columnLetter($columnIndex).$rowIndex;
            $xml .= $this->cellXml($ref, (string) $value, $isHeader);
        }

        return $xml.'</row>';
    }

    private function cellXml(string $ref, string $value, bool $isHeader): string
    {
        $style = $isHeader ? ' s="1"' : '';

        if (! $isHeader && $this->isSafeNumber($value)) {
            return '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
        }

        return '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape($value)
            .'</t></is></c>';
    }

    private function isSafeNumber(string $value): bool
    {
        if ($value === '' || ! is_numeric($value)) {
            return false;
        }

        // Preserve leading-zero identifiers (e.g. "007") and scientific notation as text.
        if ($value !== '0' && preg_match('/^-?0\d/', $value) === 1) {
            return false;
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<array{name?: string, headers?: list<string>, rows?: iterable<int, list<string>>}>  $sheets
     */
    private function workbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>';

        $usedNames = [];
        foreach (array_values($sheets) as $index => $sheet) {
            $name = $this->uniqueSheetName((string) ($sheet['name'] ?? 'Sheet'.($index + 1)), $usedNames);
            $usedNames[] = mb_strtolower($name);
            $xml .= '<sheet name="'.$this->escape($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return $xml.'</sheets></workbook>';
    }

    private function workbookRelsXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }

        $stylesRid = $sheetCount + 1;

        return $xml
            .'<Relationship Id="rId'.$stylesRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<string>  $usedLowerNames
     */
    private function uniqueSheetName(string $name, array $usedLowerNames): string
    {
        $base = $this->normalizeSheetName($name);
        $candidate = $base;
        $suffix = 2;

        while (in_array(mb_strtolower($candidate), $usedLowerNames, true)) {
            $trimmed = mb_substr($base, 0, 31 - mb_strlen((string) $suffix));
            $candidate = $trimmed.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function normalizeSheetName(string $name): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', trim($name)) ?? 'Sheet1';
        $clean = trim($clean) === '' ? 'Sheet1' : trim($clean);

        return mb_substr($clean, 0, 31);
    }
}
