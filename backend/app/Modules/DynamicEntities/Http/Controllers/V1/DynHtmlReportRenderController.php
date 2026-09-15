<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSystemConfigService;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use App\Modules\DynamicEntities\Services\DynReportBuilderService;
use App\Modules\DynamicEntities\Support\DynReportBuilderHtmlGenerator;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Public-to-tenant render payload for a saved HTML report (authenticated).
 */
class DynHtmlReportRenderController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $slug,
        DynHtmlReportService $reports,
        DynReportBuilderService $builder,
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
        $builderJson = is_array($detail['builder_json'] ?? null) ? $detail['builder_json'] : null;

        // Builder-backed reports: regenerate shell JS (no iframe same-origin fetch) and embed live rows.
        $previewPayload = null;
        if ($builderJson !== null && $builderJson !== []) {
            $generated = DynReportBuilderHtmlGenerator::generate($builderJson);
            $html = strtr($generated['html'], $tokens);
            $css = $generated['css'];
            $js = $generated['js'];

            $user = $request->user();
            if ($user instanceof TenantUser) {
                try {
                    $previewPayload = $builder->preview($builderJson, $user);
                } catch (Throwable) {
                    $previewPayload = null;
                }
            }
        }

        $embeddedData = '';
        if (is_array($previewPayload)) {
            $json = json_encode($previewPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $embeddedData = '<script type="application/json" id="report-builder-data">'.$json.'</script>';
            }
        }

        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            .htmlspecialchars((string) $detail['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'</title><style>'.$css.'</style></head><body>'
            .$html
            .$embeddedData
            .'<script>'.$js.'</script></body></html>';

        return $this->ok([
            'id' => $detail['id'],
            'name' => $detail['name'],
            'slug' => $detail['slug'],
            'document_html' => $document,
            'html_source' => $detail['html_source'],
            'css_source' => $detail['css_source'],
            'js_source' => $detail['js_source'],
            'has_builder' => $builderJson !== null && $builderJson !== [],
        ]);
    }
}
