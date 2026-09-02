<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSystemConfigService;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public-to-tenant render payload for a saved HTML report (authenticated).
 */
class DynHtmlReportRenderController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $slug,
        DynHtmlReportService $reports,
        TenantSystemConfigService $system,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('html_reports:manage')
            || $request->user()?->can('dynamic_entities:view'),
            403,
        );

        $report = $reports->findBySlug($slug);
        $detail = $reports->show($report);
        $sys = $system->get();
        $company = (string) ($sys['config']['brand']['company_name'] ?? '');
        if ($company === '') {
            $company = (string) ($sys['tenant_slug'] ?? 'TowerOS');
        }

        $tokens = [
            '{{system.company_name}}' => $company,
            '{{current_date}}' => now()->format('Y-m-d'),
            '{{report.name}}' => (string) $detail['name'],
            '{{report.slug}}' => (string) $detail['slug'],
        ];

        $html = strtr((string) $detail['html_source'], $tokens);
        $css = (string) $detail['css_source'];
        $js = (string) $detail['js_source'];

        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            .htmlspecialchars((string) $detail['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'</title><style>'.$css.'</style></head><body>'
            .$html
            .'<script>'.$js.'</script></body></html>';

        return $this->ok([
            'id' => $detail['id'],
            'name' => $detail['name'],
            'slug' => $detail['slug'],
            'document_html' => $document,
            'html_source' => $detail['html_source'],
            'css_source' => $detail['css_source'],
            'js_source' => $detail['js_source'],
        ]);
    }
}
