<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use ZipArchive;

/**
 * Reads worksheets from a minimal XLSX file (shared strings + sheet XML).
 */
final class SimpleXlsxSheetReader
{
    /**
     * @return list<list<string|null>>
     */
    public function readRows(string $path, ?string $sheetName = null): array
    {
        $sheets = $this->readAllSheets($path);

        if ($sheetName !== null) {
            foreach ($sheets as $sheet) {
                if (strcasecmp($sheet['name'], $sheetName) === 0) {
                    return $sheet['rows'];
                }
            }

            throw new \RuntimeException("Worksheet [{$sheetName}] not found in XLSX.");
        }

        return $sheets[0]['rows'] ?? [];
    }

    /**
     * @return list<array{name: string, rows: list<list<string|null>>}>
     */
    public function readAllSheets(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Unable to open XLSX: {$path}");
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetFiles = $this->resolveSheetFiles($zip);
        $sheets = [];

        foreach ($sheetFiles as $sheetName => $sheetPath) {
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                continue;
            }

            $sheets[] = [
                'name' => $sheetName,
                'rows' => $this->parseSheetXml($sheetXml, $sharedStrings),
            ];
        }

        $zip->close();

        if ($sheets === []) {
            throw new \RuntimeException('No worksheets found in XLSX.');
        }

        return $sheets;
    }

    /**
     * @return array<string, string>
     */
    private function resolveSheetFiles(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return ['Sheet1' => 'xl/worksheets/sheet1.xml'];
        }

        $relNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $rels = new \SimpleXMLElement($relsXml);
        $targetsByRid = [];
        foreach ($rels->children($relNs) as $relationship) {
            $attrs = $relationship->attributes();
            $id = (string) ($attrs['Id'] ?? '');
            $target = (string) ($attrs['Target'] ?? '');
            if ($id === '' || $target === '') {
                continue;
            }

            $targetsByRid[$id] = 'xl/'.ltrim($target, '/');
        }

        $workbook = new \SimpleXMLElement($workbookXml);
        $mainNs = $workbook->getNamespaces(true)[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relAttrNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheets = [];
        $sheetNodes = $workbook->children($mainNs)->sheets->children($mainNs);

        foreach ($sheetNodes as $sheet) {
            $attrs = $sheet->attributes();
            $name = (string) ($attrs['name'] ?? 'Sheet');
            $sheetRel = $sheet->attributes($relAttrNs);
            $rid = $sheetRel !== null ? (string) ($sheetRel['id'] ?? '') : '';
            if ($rid !== '' && isset($targetsByRid[$rid])) {
                $sheets[$name] = $targetsByRid[$rid];
            }
        }

        if ($sheets === []) {
            $index = 1;
            foreach ($targetsByRid as $target) {
                if (str_contains($target, 'worksheets/')) {
                    $sheets['Sheet'.$index] = $target;
                    $index++;
                }
            }
        }

        return $sheets !== [] ? $sheets : ['Sheet1' => 'xl/worksheets/sheet1.xml'];
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = new \SimpleXMLElement($xml);
        $strings = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;

                continue;
            }

            $text = '';
            foreach ($si->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<list<string|null>>
     */
    private function parseSheetXml(string $xml, array $sharedStrings): array
    {
        $doc = new \SimpleXMLElement($xml);
        $rows = [];
        $globalMaxCol = 0;

        foreach ($doc->sheetData->row as $row) {
            $rowIndex = (int) ($row['r'] ?? 0);
            $cells = [];

            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colIndex = $this->columnIndexFromCellRef($ref);
                $globalMaxCol = max($globalMaxCol, $colIndex);
                $cells[$colIndex] = $this->cellValue($cell, $sharedStrings);
            }

            $rows[$rowIndex] = $cells;
        }

        if ($rows === []) {
            return [];
        }

        ksort($rows);

        $normalized = [];
        foreach ($rows as $rowIndex => $cells) {
            $line = [];
            for ($i = 0; $i <= $globalMaxCol; $i++) {
                $line[] = $cells[$i] ?? null;
            }
            $normalized[] = $line;
        }

        return $normalized;
    }

    private function columnIndexFromCellRef(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($ref), $matches)) {
            return 0;
        }

        $letters = $matches[1];
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');
        $value = isset($cell->v) ? (string) $cell->v : null;

        if ($type === 's' && $value !== null) {
            return $sharedStrings[(int) $value] ?? null;
        }

        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        return $value;
    }
}
