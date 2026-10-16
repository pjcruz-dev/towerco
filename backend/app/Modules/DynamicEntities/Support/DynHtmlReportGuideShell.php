<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Browser shell for a hand-authored HTML report: Bootstrap, Chart.js, jQuery,
 * and AppFramework.ExecuteSQL (the parent page runs the SELECT).
 */
final class DynHtmlReportGuideShell
{
    public static function document(string $name, string $html, string $css, string $js): string
    {
        $title = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            .$title
            .'</title>'
            .'<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            .'<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">'
            .'<style>'.$css.'</style>'
            .'<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>'
            .'<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
            .'</head><body>'
            .$html
            .'<script>'.self::shim().'</script>'
            .'<script>'.$js.'</script></body></html>';
    }

    private static function shim(): string
    {
        return <<<'JS'
window.AppFramework = window.AppFramework || {};
window.AppFramework._loading = false;
window.AppFramework.ExecuteSQL = function (sql, label) {
  return new Promise(function (resolve, reject) {
    var id = 'q-' + Math.random().toString(36).slice(2);
    var timer = setTimeout(function () {
      window.removeEventListener('message', onMessage);
      resolve([]);
    }, 20000);
    function onMessage(event) {
      var data = event.data;
      if (!data || data.type !== 'toweros-html-report-sql-result' || data.id !== id) return;
      clearTimeout(timer);
      window.removeEventListener('message', onMessage);
      if (data.error) reject(new Error(data.error));
      else resolve(Array.isArray(data.rows) ? data.rows : []);
    }
    window.addEventListener('message', onMessage);
    var target = window.parent && window.parent !== window ? window.parent : window;
    target.postMessage({ type: 'toweros-html-report-sql', id: id, sql: String(sql || ''), label: label || '' }, '*');
  });
};
JS;
    }
}
