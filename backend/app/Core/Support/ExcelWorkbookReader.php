<?php

declare(strict_types=1);

namespace App\Core\Support;

use Carbon\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Reads the first worksheet of an xlsx file produced by Excel or ExcelWorkbookWriter.
 */
final class ExcelWorkbookReader
{
    /**
     * @return list<list<string>>
     */
    public function readFirstSheet(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'toweros-xlsx-in-');
        if ($tmp === false) {
            throw new RuntimeException('Unable to read the spreadsheet.');
        }
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new RuntimeException('The spreadsheet could not be opened.');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml)) {
            $shared = $this->sharedStrings($sharedXml);
        }
        $dateStyles = [];
        $stylesXml = $zip->getFromName('xl/styles.xml');
        if (is_string($stylesXml)) {
            $dateStyles = $this->dateStyleIndexes($stylesXml);
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);
        if (! is_string($sheet)) {
            throw new RuntimeException('The spreadsheet has no first sheet.');
        }

        return $this->rows($sheet, $shared, $dateStyles);
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(string $xml): array
    {
        $document = $this->document($xml);
        $strings = [];
        foreach ($document->getElementsByTagName('si') as $item) {
            $text = '';
            foreach ($item->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @param  array<int, true>  $dateStyles
     * @return list<list<string>>
     */
    private function rows(string $xml, array $shared, array $dateStyles): array
    {
        $document = $this->document($xml);
        $rows = [];
        foreach ($document->getElementsByTagName('row') as $row) {
            $values = [];
            $cursor = 0;
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof \DOMElement || $cell->localName !== 'c') {
                    continue;
                }
                $index = $this->columnIndex($cell->getAttribute('r'));
                if ($index === null) {
                    $index = $cursor;
                }
                $cursor = $index + 1;
                $values[$index] = $this->cellValue($cell, $shared, $dateStyles);
            }
            if ($values === []) {
                $rows[] = [];

                continue;
            }
            $line = array_fill(0, max(array_keys($values)) + 1, '');
            foreach ($values as $index => $value) {
                $line[$index] = $value;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $shared
     * @param  array<int, true>  $dateStyles
     */
    private function cellValue(\DOMElement $cell, array $shared, array $dateStyles): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            return $this->textNodes($cell);
        }
        if ($type === 's') {
            $index = (int) ($cell->getElementsByTagName('v')->item(0)?->textContent ?? '0');

            return $shared[$index] ?? '';
        }

        $value = (string) ($cell->getElementsByTagName('v')->item(0)?->textContent ?? '');
        $style = $cell->getAttribute('s');
        if ($value !== '' && $style !== '' && isset($dateStyles[(int) $style]) && is_numeric($value)) {
            return $this->excelDate((float) $value);
        }

        return $value;
    }

    private function textNodes(\DOMElement $cell): string
    {
        $text = '';
        foreach ($cell->getElementsByTagName('t') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    private function columnIndex(string $reference): ?int
    {
        if (preg_match('/^([A-Z]+)/i', $reference, $matches) !== 1) {
            return null;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @return array<int, true>
     */
    private function dateStyleIndexes(string $xml): array
    {
        $document = $this->document($xml);
        $customDates = [];
        foreach ($document->getElementsByTagName('numFmt') as $format) {
            $code = strtolower($format->getAttribute('formatCode'));
            if (preg_match('/[dmy]/', $code) === 1 && ! str_contains($code, '#')) {
                $customDates[(int) $format->getAttribute('numFmtId')] = true;
            }
        }

        $cellXfs = null;
        foreach ($document->getElementsByTagName('cellXfs') as $node) {
            $cellXfs = $node;
            break;
        }
        if (! $cellXfs instanceof \DOMElement) {
            return [];
        }

        $indexes = [];
        $position = 0;
        foreach ($cellXfs->childNodes as $xf) {
            if (! $xf instanceof \DOMElement || $xf->localName !== 'xf') {
                continue;
            }
            $formatId = (int) $xf->getAttribute('numFmtId');
            if (($formatId >= 14 && $formatId <= 22) || ($formatId >= 45 && $formatId <= 47) || isset($customDates[$formatId])) {
                $indexes[$position] = true;
            }
            $position++;
        }

        return $indexes;
    }

    private function excelDate(float $serial): string
    {
        $days = (int) floor($serial);

        return Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC')
            ->addDays($days)
            ->toDateString();
    }

    private function document(string $xml): \DOMDocument
    {
        $document = new \DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);

        return $document;
    }
}
