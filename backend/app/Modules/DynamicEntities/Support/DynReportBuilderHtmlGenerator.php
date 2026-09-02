<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Turns a Report Builder definition into HTML/CSS/JS shells for Manage HTML Reports.
 *
 * @phpstan-type BuilderDef array<string, mixed>
 */
final class DynReportBuilderHtmlGenerator
{
    /**
     * @param  BuilderDef  $def
     * @return array{html: string, css: string, js: string}
     */
    public static function generate(array $def): array
    {
        $title = htmlspecialchars((string) ($def['title'] ?? 'Report'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $caption = htmlspecialchars((string) ($def['caption'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $format = htmlspecialchars((string) ($def['format'] ?? 'summary'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $entity = htmlspecialchars((string) ($def['entity_slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $currency = htmlspecialchars((string) ($def['currency_prefix'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $defJson = json_encode($def, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($defJson === false) {
            $defJson = '{}';
        }

        $html = <<<HTML
<div class="report-shell" data-report-builder="1">
  <header class="report-header">
    <div class="company">{{system.company_name}}</div>
    <div class="date">{{current_date}}</div>
  </header>
  <h1>{$title}</h1>
  <p class="muted caption">{$caption}</p>
  <p class="meta">Format: <strong>{$format}</strong> · Source: <code>{$entity}</code></p>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print PDF</button>
    <button type="button" id="btn-export-csv">Export CSV</button>
    <button type="button" id="btn-refresh">Refresh</button>
  </div>
  <div id="chart-wrap" class="chart-wrap" hidden>
    <canvas id="report-chart" height="120"></canvas>
  </div>
  <div class="card table-responsive">
    <table class="report-table" id="report-table">
      <thead id="report-thead"><tr><th>Loading…</th></tr></thead>
      <tbody id="report-tbody"></tbody>
      <tfoot id="report-tfoot"></tfoot>
    </table>
  </div>
  <script type="application/json" id="report-builder-def">{$defJson}</script>
</div>
HTML;

        $css = <<<'CSS'
.report-shell { font-family: Inter, system-ui, sans-serif; color: #0f172a; max-width: 1100px; margin: 0 auto; padding: 24px; }
.report-header { display: flex; justify-content: space-between; color: #64748b; font-size: 12px; margin-bottom: 12px; }
h1 { font-size: 22px; font-weight: 600; margin: 0 0 6px; }
.muted, .caption { color: #64748b; font-size: 14px; margin: 0 0 8px; }
.meta { font-size: 12px; color: #64748b; margin-bottom: 12px; }
.toolbar { display: flex; gap: 8px; margin: 12px 0 16px; }
.toolbar button { border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 13px; }
.card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 0; background: #fff; overflow: hidden; }
.report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.report-table th, .report-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
.report-table th { background: #f8fafc; font-weight: 600; }
.report-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.report-table tfoot td { font-weight: 600; background: #f8fafc; }
.chart-wrap { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; }
@media print {
  .no-print { display: none !important; }
  .card { border: none; }
  .report-table th { border-top: 2px solid #000; border-bottom: 2px solid #000; }
}
CSS;

        $js = <<<JS
(function () {
  const defEl = document.getElementById('report-builder-def');
  const def = defEl ? JSON.parse(defEl.textContent || '{}') : {};
  const currency = def.currency_prefix || '{$currency}' || '';
  const thead = document.getElementById('report-thead');
  const tbody = document.getElementById('report-tbody');
  const tfoot = document.getElementById('report-tfoot');

  function fmt(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return String(n ?? '');
    const s = num.toLocaleString(undefined, { maximumFractionDigits: 2 });
    return currency ? (currency + s) : s;
  }

  async function load() {
    try {
      const slug = def.saved_slug || '';
      const res = await fetch('/api/v1/dynamic-entities/report-builder/preview', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1]
            ? decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1])
            : '',
        },
        body: JSON.stringify(def),
      });
      if (!res.ok) throw new Error('Preview failed (' + res.status + ')');
      const json = await res.json();
      const data = json.data || json;
      const columns = data.columns || [];
      const rows = data.rows || [];
      const totals = data.totals || {};

      thead.innerHTML = '<tr>' + columns.map(c => '<th class="' + (c.numeric ? 'num' : '') + '">' + (c.label || c.key) + '</th>').join('') + '</tr>';
      tbody.innerHTML = rows.length
        ? rows.map(r => '<tr>' + columns.map(c => {
            const v = r[c.key];
            const text = c.numeric ? fmt(v) : String(v ?? '');
            return '<td class="' + (c.numeric ? 'num' : '') + '">' + text + '</td>';
          }).join('') + '</tr>').join('')
        : '<tr><td colspan="' + Math.max(columns.length, 1) + '">No matching records.</td></tr>';

      if (totals && Object.keys(totals).length) {
        tfoot.innerHTML = '<tr>' + columns.map(c => {
          if (c.key === columns[0]?.key) return '<td>Total</td>';
          if (c.numeric && totals[c.key] != null) return '<td class="num">' + fmt(totals[c.key]) + '</td>';
          return '<td></td>';
        }).join('') + '</tr>';
      } else {
        tfoot.innerHTML = '';
      }
    } catch (e) {
      tbody.innerHTML = '<tr><td>Unable to load live data. Open this report from the TowerOS workspace while signed in. ' + (e && e.message ? e.message : '') + '</td></tr>';
    }
  }

  document.getElementById('btn-refresh')?.addEventListener('click', load);
  document.getElementById('btn-export-csv')?.addEventListener('click', function () {
    const table = document.getElementById('report-table');
    if (!table) return;
    const lines = [];
    table.querySelectorAll('tr').forEach(tr => {
      if (tr.offsetParent === null && tr.style.display === 'none') return;
      const cells = Array.from(tr.querySelectorAll('th,td')).map(td => '"' + String(td.textContent || '').replace(/"/g, '""') + '"');
      if (cells.length) lines.push(cells.join(','));
    });
    const blob = new Blob(['\\ufeff' + lines.join('\\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (def.title || 'report') + '.csv';
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });

  load();
})();
JS;

        return ['html' => $html, 'css' => $css, 'js' => $js];
    }
}
