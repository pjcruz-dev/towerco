<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Shared printable HTML table for uncapped module-list print exports.
 */
final class ModuleListPrintableHtml
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int|string, mixed>>  $rows  Associative (header keys) or positional rows
     */
    public static function render(string $title, array $headers, iterable $rows, ?int $matchedRows = null): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $thead = '';
        foreach ($headers as $header) {
            $thead .= '<th>'.htmlspecialchars((string) $header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</th>';
        }

        $tbody = '';
        $exported = 0;
        foreach ($rows as $row) {
            $exported++;
            $tbody .= '<tr>';
            $isList = array_is_list($row);
            foreach ($headers as $index => $header) {
                $cell = $isList
                    ? ($row[$index] ?? '')
                    : ($row[$header] ?? '');
                $tbody .= '<td>'.htmlspecialchars((string) $cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            }
            $tbody .= '</tr>';
        }

        $metaParts = [number_format($exported).' row(s) in this file'];
        if ($matchedRows !== null) {
            $metaParts[] = number_format($matchedRows).' matching row(s)';
        }
        $meta = htmlspecialchars(implode(' · ', $metaParts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>{$safeTitle}</title>
<style>
  body { font-family: system-ui, Segoe UI, sans-serif; font-size: 12px; color: #0f172a; margin: 1.5rem; }
  h1 { font-size: 18px; font-weight: 600; margin: 0 0 0.35rem; }
  .meta { color: #64748b; margin-bottom: 1rem; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #f8fafc; font-weight: 600; }
  @media print {
    body { margin: 0.5rem; }
    th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
  <h1>{$safeTitle}</h1>
  <p class="meta">{$meta}</p>
  <table>
    <thead><tr>{$thead}</tr></thead>
    <tbody>{$tbody}</tbody>
  </table>
  <script>window.addEventListener("load", function () { window.print(); });</script>
</body>
</html>
HTML;
    }
}
