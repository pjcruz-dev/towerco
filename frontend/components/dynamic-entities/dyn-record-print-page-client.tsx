"use client";

import Link from "next/link";
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type RefObject } from "react";
import { Minus, Pencil, Plus, Printer } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { TenantBrandMark } from "@/components/layout/tenant-brand-mark";
import { BirForm2307Print } from "@/components/dynamic-entities/bir-form-2307-print";
import { Button } from "@/components/ui/button";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import {
  fetchDynRecord,
  type DynField,
  type DynPrintSettings,
  type DynRecordDetail,
  type DynResolvedRelation,
} from "@/lib/api/modules/dynamic-entities-api";
import { renderDynPrintCss, renderDynPrintTemplate, buildSystemDateTokens } from "@/lib/dynamic-entities/dyn-print-template";
import { resolvePrintTemplate } from "@/lib/dynamic-entities/dyn-print-templates";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";

type RelatedBlock = {
  title: string;
  field: string;
  titleText: string | null;
  values: Record<string, unknown>;
  labels: Record<string, string>;
};

type Props = {
  /** One or more record IDs — multiple IDs render as a single merged print document. */
  recordIds: string[];
  /** Optional printable template id (multi-template per entity). */
  templateId?: string | null;
};

type PaperSize = "a4" | "letter" | "legal" | "a3" | "a5" | "b5";
type Orientation = "portrait" | "landscape";
type MarginPreset = "default" | "narrow" | "wide" | "none";
type ZoomMode = "fit-page" | "fit-width" | 50 | 75 | 100 | 150 | 200 | 300;

const PAPER_MM: Record<PaperSize, { w: number; h: number; label: string }> = {
  a4: { w: 210, h: 297, label: "A4" },
  letter: { w: 215.9, h: 279.4, label: "Letter" },
  legal: { w: 215.9, h: 355.6, label: "Legal" },
  a3: { w: 297, h: 420, label: "A3" },
  a5: { w: 148, h: 210, label: "A5" },
  b5: { w: 176, h: 250, label: "B5" },
};

const MARGIN_MM: Record<MarginPreset, number> = {
  default: 12,
  narrow: 6,
  wide: 20,
  none: 0,
};

const ZOOM_STEPS: Array<50 | 75 | 100 | 150 | 200 | 300> = [50, 75, 100, 150, 200, 300];

const toolbarSelectClass =
  "h-8 rounded-md border border-slate-600 bg-slate-800 px-2.5 text-xs font-medium text-slate-100 outline-none hover:border-slate-500 focus:border-sky-500";

export function DynRecordPrintPageClient({ recordIds, templateId = null }: Props) {
  const organizationLabel = useOrganizationLabel();
  const ids = useMemo(
    () => Array.from(new Set(recordIds.map((id) => id.trim()).filter(Boolean))).slice(0, 50),
    [recordIds],
  );
  const [items, setItems] = useState<Array<{ record: DynRecordDetail; relatedBlocks: RelatedBlock[] }>>(
    [],
  );
  const [error, setError] = useState<string | null>(null);
  const [paper, setPaper] = useState<PaperSize>("a4");
  const [orientation, setOrientation] = useState<Orientation>("portrait");
  const [margin, setMargin] = useState<MarginPreset>("default");
  const [zoomMode, setZoomMode] = useState<ZoomMode>("fit-page");
  const [previewScale, setPreviewScale] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const stageRef = useRef<HTMLDivElement | null>(null);
  const sheetRef = useRef<HTMLElement | null>(null);

  const record = items[0]?.record ?? null;
  const relatedBlocks = items[0]?.relatedBlocks ?? [];
  const backHref =
    items.length === 1
      ? `/dynamic-entities/records/${ids[0]}`
      : items[0]?.record.entity.slug
        ? `/dynamic-entities/${items[0].record.entity.slug}`
        : "/dynamic-entities";

  useEffect(() => {
    let cancelled = false;
    if (ids.length === 0) {
      setError("No records selected for print.");
      setItems([]);
      return;
    }

    (async () => {
      try {
        const loaded: Array<{ record: DynRecordDetail; relatedBlocks: RelatedBlock[] }> = [];
        for (const id of ids) {
          const row = await fetchDynRecord(id);
          if (cancelled) return;
          const settings = resolvePrintTemplate(
            row.entity.print_settings,
            templateId,
            row.entity.name,
          );
          const expands = settings?.related_expand ?? [];
          const blocks: RelatedBlock[] = [];
          for (const expand of expands) {
            const relatedId = row.values[expand.field];
            if (typeof relatedId !== "string" || relatedId.length < 8) continue;
            try {
              const related = await fetchDynRecord(relatedId);
              if (cancelled) return;
              const labels: Record<string, string> = {};
              for (const f of related.entity.fields) {
                labels[f.name] = f.label;
              }
              const values: Record<string, unknown> = {};
              for (const key of expand.fields) {
                if (related.values[key] !== undefined) {
                  values[key] = related.values[key] ?? null;
                }
              }
              blocks.push({
                title: expand.title,
                field: expand.field,
                titleText: related.title,
                values,
                labels,
              });
            } catch {
              // Related record may be missing after partial ETL.
            }
          }
          loaded.push({ record: row, relatedBlocks: blocks });
        }
        if (!cancelled) {
          setItems(loaded);
          setError(null);
        }
      } catch {
        if (!cancelled) setError("Unable to load record(s) for print.");
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [ids, templateId]);

  const print = resolvePrintTemplate(
    record?.entity.print_settings,
    templateId,
    record?.entity.name ?? "Print",
  );
  const groups = useMemo(
    () =>
      [...(record?.entity.field_groups ?? [])]
        .filter((g) => g.applies_to_view || g.applies_to_form)
        .sort((a, b) => a.sort_order - b.sort_order),
    [record],
  );
  const fields = useMemo(
    () =>
      [...(record?.entity.fields ?? [])].filter(
        (f) => !f.is_system_field && !["actions", "workflows", "print", "id"].includes(f.name),
      ),
    [record],
  );

  const companyName = print?.company_name?.trim() || organizationLabel;
  const branding = useTenantBrandingStore((s) => s.branding);
  const companyAddressBlock = useMemo(() => {
    const fromPrint = print?.company_address?.trim();
    if (fromPrint) return fromPrint;

    const address = branding?.company_address?.trim() || "";
    const phone = branding?.company_phone?.trim() || "";
    const email = branding?.company_email?.trim() || "";
    const tin = branding?.company_tin?.trim() || "";
    if (!address && !phone && !email && !tin) return null;

    const lines: string[] = [];
    if (address) lines.push(address);
    if (phone || email) {
      lines.push(`Phone: ${phone || ""} | Email: ${email || ""}`);
    }
    if (tin) lines.push(`TIN: ${tin}`);
    return lines.join("\n");
  }, [print?.company_address, branding]);
  const listLabel = print?.list_label || "Print";
  const documentTitle = print?.document_title || record?.entity.name || "Record";
  const isAgreement = print?.layout === "agreement";
  const isStatusReport =
    print?.layout === "status_report" || print?.layout === "saq_status_report";
  const isBir2307 = print?.layout === "bir_2307";
  const relations = record?.resolved_relations ?? {};

  useEffect(() => {
    if (isBir2307) {
      setPaper("legal");
      setOrientation("portrait");
      setMargin("none");
      return;
    }
    const o = print?.orientation;
    if (o === "portrait" || o === "landscape") {
      setOrientation(o);
    }
  }, [isBir2307, print?.orientation]);

  const siteBlock = relatedBlocks.find((b) => b.field === "tower_site_id");
  const vendorBlock = relatedBlocks.find((b) =>
    ["saq_vendor_id", "power_vendor_id", "cme_vendor_id", "saq_contractor_id"].includes(b.field),
  );
  const lessorBlock = relatedBlocks.find((b) => b.field === "lessor_id");
  const utilityBlock = relatedBlocks.find((b) => b.field === "electric_utility_id");

  const siteSubtitle = useMemo(() => {
    if (!record) return null;
    const siteName =
      (siteBlock?.values.site_name as string | undefined)?.trim() ||
      siteBlock?.titleText ||
      record.title;
    return siteName || null;
  }, [record, siteBlock]);

  const subtitle = useMemo(() => {
    if (!record || !print) return null;
    if (isStatusReport) return siteSubtitle;
    const parts = (print.subtitle_fields ?? [])
      .map((name) => formatValue(record.values[name], name, print, relations))
      .filter((v) => v !== "—");
    if (parts.length === 0) return null;
    const joined = parts.join(" | ");
    return print.subtitle_prefix ? `${print.subtitle_prefix} ${joined}` : joined;
  }, [record, print, isStatusReport, siteSubtitle, relations]);

  const lessorName =
    firstNonEmpty(
      lessorBlock?.values.lessor_name,
      lessorBlock?.values.name,
      lessorBlock?.titleText,
      relations.lessor_id?.title,
    ) || "—";

  const siteCode = firstNonEmpty(siteBlock?.values.site_code) || null;
  const statusSubtitle = useMemo(() => {
    if (!isStatusReport) return subtitle;
    if (siteCode && siteSubtitle) return `Site: | ${siteCode}`;
    if (siteCode) return `Site: | ${siteCode}`;
    return siteSubtitle;
  }, [isStatusReport, siteCode, siteSubtitle, subtitle]);

  const dims = PAPER_MM[paper];
  const widthMm = orientation === "landscape" ? dims.h : dims.w;
  const heightMm = orientation === "landscape" ? dims.w : dims.h;
  const marginMm = MARGIN_MM[margin];
  const pageSizeCss = `${paper === "letter" || paper === "legal" ? paper : dims.label} ${orientation}`;
  const paperWidth = `${widthMm}mm`;
  const paperHeight = `${heightMm}mm`;

  useLayoutEffect(() => {
    function recompute() {
      const stage = stageRef.current;
      const sheet = sheetRef.current;
      if (!stage || !sheet) return;
      const stageW = stage.clientWidth;
      const stageH = stage.clientHeight;
      const sheetW = sheet.offsetWidth || 1;
      const sheetBox = sheet.offsetHeight || 1;
      let next = 1;
      if (zoomMode === "fit-width") next = Math.min(1.5, (stageW - 48) / sheetW);
      else if (zoomMode === "fit-page") next = Math.min((stageW - 48) / sheetW, (stageH - 48) / sheetBox);
      else next = zoomMode / 100;
      setPreviewScale(Math.max(0.25, Math.min(3, next)));
      // Sheet min-height is full paper size (margins are padding). Count pages against full page height
      // — using content-only height falsely reports 2 pages for a single A4 sheet.
      const pagePx = Math.max(1, heightMm * (96 / 25.4));
      setPageCount(Math.max(1, Math.ceil(sheet.scrollHeight / pagePx - 0.02)));
    }
    recompute();
    const ro = typeof ResizeObserver !== "undefined" ? new ResizeObserver(() => recompute()) : null;
    if (stageRef.current) ro?.observe(stageRef.current);
    if (sheetRef.current) ro?.observe(sheetRef.current);
    window.addEventListener("resize", recompute);
    return () => {
      ro?.disconnect();
      window.removeEventListener("resize", recompute);
    };
  }, [zoomMode, paper, orientation, margin, record, relatedBlocks, heightMm, marginMm]);

  function nudgeZoom(dir: -1 | 1) {
    if (zoomMode === "fit-page" || zoomMode === "fit-width") {
      setZoomMode(100);
      return;
    }
    const idx = ZOOM_STEPS.indexOf(zoomMode);
    setZoomMode(ZOOM_STEPS[Math.max(0, Math.min(ZOOM_STEPS.length - 1, idx + dir))]!);
  }

  return (
    <PermissionGate requiredPermissions={[permissions.printablesManage]}>
      <div className="dyn-print-root min-h-screen bg-[#404040] print:min-h-0 print:!bg-white">
        <header
          className="dyn-print-toolbar sticky top-0 z-10 border-b border-black/40 bg-[#1e1e1e] text-neutral-50 print:hidden"
        >
          <div className="flex flex-wrap items-center gap-2 px-3 py-2 sm:px-4">
            <Link
              href={backHref}
              className="mr-1 shrink-0 text-xs text-slate-400 underline-offset-2 hover:text-white hover:underline"
            >
              Back
            </Link>

            {record?.entity.slug ? (
              <Link
                href={`/dynamic-entities/printables/edit?entity=${encodeURIComponent(record.entity.slug)}${
                  templateId ? `&template=${encodeURIComponent(templateId)}` : ""
                }`}
                className="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-600 bg-slate-800 px-2.5 text-xs font-medium text-slate-100 hover:border-slate-500"
              >
                <Pencil className="size-3.5" />
                Edit Template
              </Link>
            ) : null}

            <select
              className={toolbarSelectClass}
              value={paper}
              onChange={(e) => setPaper(e.target.value as PaperSize)}
              aria-label="Paper size"
            >
              {(Object.keys(PAPER_MM) as PaperSize[]).map((key) => (
                <option key={key} value={key}>
                  {PAPER_MM[key].label}
                </option>
              ))}
            </select>

            <select
              className={toolbarSelectClass}
              value={orientation}
              onChange={(e) => setOrientation(e.target.value as Orientation)}
              aria-label="Orientation"
            >
              <option value="portrait">Portrait</option>
              <option value="landscape">Landscape</option>
            </select>

            <select
              className={toolbarSelectClass}
              value={margin}
              onChange={(e) => setMargin(e.target.value as MarginPreset)}
              aria-label="Margins"
            >
              <option value="default">Default · 12mm</option>
              <option value="narrow">Narrow · 6mm</option>
              <option value="wide">Wide · 20mm</option>
              <option value="none">None</option>
            </select>

            <div className="flex items-center gap-0.5">
              <button
                type="button"
                className="flex size-8 items-center justify-center rounded-md border border-slate-600 bg-slate-800 text-slate-100 hover:border-slate-500"
                onClick={() => nudgeZoom(-1)}
                aria-label="Zoom out"
              >
                <Minus className="size-3.5" />
              </button>
              <select
                className={cn(toolbarSelectClass, "min-w-[6.5rem]")}
                value={zoomMode}
                onChange={(e) => {
                  const v = e.target.value;
                  if (v === "fit-page" || v === "fit-width") setZoomMode(v);
                  else setZoomMode(Number(v) as ZoomMode);
                }}
                aria-label="Zoom"
              >
                <option value="fit-width">Fit width</option>
                <option value="fit-page">Fit page</option>
                {ZOOM_STEPS.map((z) => (
                  <option key={z} value={z}>
                    {z}%
                  </option>
                ))}
              </select>
              <button
                type="button"
                className="flex size-8 items-center justify-center rounded-md border border-slate-600 bg-slate-800 text-slate-100 hover:border-slate-500"
                onClick={() => nudgeZoom(1)}
                aria-label="Zoom in"
              >
                <Plus className="size-3.5" />
              </button>
            </div>

            <span className="rounded-md border border-slate-600 bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-200">
              {items.length > 1
                ? `${items.length} documents`
                : `${pageCount} ${pageCount === 1 ? "page" : "pages"}`}
            </span>

            <div className="ml-auto flex items-center gap-2">
              <p className="hidden max-w-[14rem] truncate text-xs text-slate-400 lg:block">
                {listLabel}
                {siteSubtitle ? ` · ${siteSubtitle}` : ""}
              </p>
              <Button
                size="sm"
                className="h-8 rounded-md bg-sky-600 px-3 text-xs font-medium text-white hover:bg-sky-500"
                onClick={() => window.print()}
              >
                <Printer className="size-3.5" />
                Print Document
              </Button>
            </div>
          </div>
        </header>

        <div
          ref={stageRef}
          className="dyn-print-stage flex h-[calc(100vh-3.25rem)] justify-center overflow-auto px-4 py-6 print:m-0 print:h-auto print:max-w-none print:overflow-visible print:bg-white print:p-0"
        >
          {error ? <p className="text-sm text-red-200 print:hidden">{error}</p> : null}

          {items.length > 0 ? (
            <div className="flex flex-col items-center gap-3">
              <div
                className="dyn-print-scale origin-top space-y-8 print:!m-0 print:!w-full print:!max-w-none print:!transform-none print:space-y-0"
                style={{
                  width: paperWidth,
                  transform: `scale(${previewScale})`,
                }}
              >
                {items.map((item, index) => (
                  <DynPrintRecordSheet
                    key={item.record.id}
                    record={item.record}
                    relatedBlocks={item.relatedBlocks}
                    templateId={templateId}
                    sheetRef={index === 0 ? sheetRef : undefined}
                    paperHeight={paperHeight}
                    marginMm={marginMm}
                    organizationLabel={organizationLabel}
                    companyAddressBlock={companyAddressBlock}
                    isLast={index === items.length - 1}
                  />
                ))}
              </div>
              <p className="max-w-xl text-center text-[11px] text-slate-400 print:hidden">
                {items.length} {items.length === 1 ? "record" : "records"} ·{" "}
                {new Date().toLocaleDateString(undefined, {
                  month: "long",
                  day: "numeric",
                  year: "numeric",
                })}{" "}
                · {PAPER_MM[paper].label} {orientation} · {widthMm} × {heightMm} mm · margins{" "}
                {marginMm}/{marginMm}/{marginMm}/{marginMm} mm
              </p>
            </div>
          ) : !error ? (
            <p className="text-sm text-slate-200 print:hidden">Loading print template…</p>
          ) : null}
        </div>

        <style
          dangerouslySetInnerHTML={{
            __html: `
              @media print {
                @page { size: ${pageSizeCss}; margin: ${marginMm}mm; }
                html, body {
                  background: #fff !important;
                  color: #0f172a !important;
                  -webkit-print-color-adjust: exact;
                  print-color-adjust: exact;
                }
                .dyn-print-root,
                .dyn-print-stage {
                  background: #fff !important;
                  min-height: 0 !important;
                  height: auto !important;
                  overflow: visible !important;
                  margin: 0 !important;
                  padding: 0 !important;
                }
                .dyn-print-toolbar {
                  display: none !important;
                }
                .dyn-print-scale {
                  width: 100% !important;
                  max-width: none !important;
                  transform: none !important;
                  margin: 0 !important;
                }
                .dyn-print-sheet {
                  min-height: 0 !important;
                  width: 100% !important;
                  max-width: none !important;
                  margin: 0 !important;
                  padding: 0 !important;
                  border: none !important;
                  box-shadow: none !important;
                  background: #fff !important;
                  color: #0f172a !important;
                }
                .dyn-print-sheet-break {
                  break-after: page;
                  page-break-after: always;
                }
                .dyn-print-field-grid {
                  display: grid !important;
                  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                  gap: 0.75rem 1.25rem !important;
                }
                .dyn-print-letterhead {
                  display: flex !important;
                  align-items: flex-start !important;
                  justify-content: space-between !important;
                  gap: 1rem !important;
                }
                .dyn-print-letterhead-meta {
                  text-align: right !important;
                  max-width: 70% !important;
                }
              }
            `,
          }}
        />
      </div>
    </PermissionGate>
  );
}

function DynPrintRecordSheet({
  record,
  relatedBlocks,
  templateId,
  sheetRef,
  paperHeight,
  marginMm,
  organizationLabel,
  companyAddressBlock,
  isLast,
}: {
  record: DynRecordDetail;
  relatedBlocks: RelatedBlock[];
  templateId?: string | null;
  sheetRef?: RefObject<HTMLElement | null>;
  paperHeight: string;
  marginMm: number;
  organizationLabel: string;
  companyAddressBlock: string | null;
  isLast: boolean;
}) {
  const print = resolvePrintTemplate(record.entity.print_settings, templateId, record.entity.name);
  const groups = [...(record.entity.field_groups ?? [])]
    .filter((g) => g.applies_to_view || g.applies_to_form)
    .sort((a, b) => a.sort_order - b.sort_order);
  const fields = [...(record.entity.fields ?? [])].filter(
    (f) => !f.is_system_field && !["actions", "workflows", "print", "id"].includes(f.name),
  );
  const fieldGroupId = (f: (typeof fields)[number]) => f.form_group_id ?? f.view_group_id ?? null;
  const ungroupedFields = fields
    .filter((f) => !fieldGroupId(f) || !groups.some((g) => g.id === fieldGroupId(f)))
    .sort((a, b) => a.field_order - b.field_order);
  const companyName = print?.company_name?.trim() || organizationLabel;
  const documentTitle = print?.document_title || record.entity.name || "Record";
  const isAgreement = print?.layout === "agreement";
  const isStatusReport =
    print?.layout === "status_report" || print?.layout === "saq_status_report";
  const isBir2307 = print?.layout === "bir_2307";
  const relations = record.resolved_relations ?? {};

  const siteBlock = relatedBlocks.find((b) => b.field === "tower_site_id");
  const vendorBlock = relatedBlocks.find((b) =>
    ["saq_vendor_id", "power_vendor_id", "cme_vendor_id", "saq_contractor_id"].includes(b.field),
  );
  const lessorBlock = relatedBlocks.find((b) => b.field === "lessor_id");
  const utilityBlock = relatedBlocks.find((b) => b.field === "electric_utility_id");

  const siteName =
    (typeof record.values.site_name === "string" && record.values.site_name) ||
    siteBlock?.titleText ||
    relations.tower_site_id?.title ||
    null;
  const siteCode =
    (typeof record.values.site_code === "string" && record.values.site_code) || null;
  const siteSubtitle =
    siteName && siteCode ? `${siteName} (${siteCode})` : siteName || siteCode;

  const statusSubtitleParts = (print?.subtitle_fields ?? [])
    .map((name) => formatValue(record.values[name], name, print, relations))
    .filter((v) => v && v !== "—");
  const statusSubtitle = isStatusReport
    ? siteSubtitle
    : statusSubtitleParts.length
      ? print?.subtitle_prefix
        ? `${print.subtitle_prefix} ${statusSubtitleParts.join(" · ")}`
        : statusSubtitleParts.join(" · ")
      : null;

  const lessorName =
    lessorBlock?.titleText ||
    (typeof lessorBlock?.values.name === "string" ? lessorBlock.values.name : null) ||
    relations.lessor_id?.title ||
    "";

  if (isBir2307) {
    return (
      <article
        ref={sheetRef}
        className={cn(
          "dyn-print-sheet border border-slate-400 bg-neutral-200 shadow-lg print:border-0 print:bg-white print:shadow-none",
          !isLast && "dyn-print-sheet-break mb-8 print:mb-0",
        )}
        style={{
          minHeight: "934px",
          padding: 0,
          width: "612px",
          maxWidth: "100%",
        }}
      >
        <BirForm2307Print record={record} />
      </article>
    );
  }

  const templateHtml = print?.template_html?.trim() ?? "";
  if (templateHtml) {
    const branding = useTenantBrandingStore.getState().branding;
    const linked: Record<string, Record<string, unknown>> = {};
    for (const [key, rel] of Object.entries(relations)) {
      linked[key] = {
        title: rel.title ?? "",
        id: rel.id ?? "",
      };
    }
    for (const block of relatedBlocks) {
      linked[block.field] = {
        ...(linked[block.field] ?? {}),
        title: block.titleText ?? linked[block.field]?.title ?? "",
        ...block.values,
      };
    }
    const items: Array<Record<string, unknown>> = [];
    for (const value of Object.values(record.values)) {
      if (Array.isArray(value) && value.length > 0 && typeof value[0] === "object") {
        for (const row of value) {
          if (row && typeof row === "object" && !Array.isArray(row)) {
            items.push(row as Record<string, unknown>);
          }
        }
      }
    }
    const logoUrl = resolveBrandingAssetUrl(branding?.logo_url) ?? "";
    const accent =
      branding?.light?.["--primary"]?.trim() ||
      branding?.light?.primary?.trim() ||
      "#2563EB";
    const ctx = {
      system: {
        ...buildSystemDateTokens(),
        company_name: companyName,
        company_address:
          print?.company_address?.trim() ||
          branding?.company_address?.trim() ||
          companyAddressBlock?.split("\n")[0] ||
          "",
        company_phone: branding?.company_phone?.trim() || "",
        company_email: branding?.company_email?.trim() || "",
        company_tin: branding?.company_tin?.trim() || "",
        company_logo: logoUrl,
        company_logo_path: logoUrl,
        prepared_by: String(record.values.prepared_by ?? record.values.prepared_by_name ?? ""),
        approved_by: String(record.values.approved_by ?? record.values.approved_by_name ?? ""),
      },
      theme: {
        accent,
      },
      record: {
        ...record.values,
        title: record.title,
        status: record.status,
        id: record.id,
      },
      linked,
      items,
    };
    const merged = renderDynPrintTemplate(templateHtml, ctx);
    const mergedCss = print?.template_css?.trim()
      ? renderDynPrintCss(print.template_css, ctx)
      : "";

    return (
      <article
        ref={sheetRef}
        className={cn(
          "dyn-print-sheet relative border border-slate-400 bg-white shadow-lg print:border-0 print:shadow-none",
          !isLast && "dyn-print-sheet-break mb-8 print:mb-0",
        )}
        style={{
          minHeight: paperHeight,
          padding: `${marginMm}mm`,
        }}
      >
        <div
          className="dyn-print-margin-guide pointer-events-none absolute border border-dashed border-sky-300/90 print:hidden"
          style={{ inset: `${marginMm}mm` }}
          aria-hidden
        />
        {mergedCss ? <style dangerouslySetInnerHTML={{ __html: mergedCss }} /> : null}
        <div className="dyn-print-template relative" dangerouslySetInnerHTML={{ __html: merged }} />
      </article>
    );
  }

  return (
    <article
      ref={sheetRef}
      className={cn(
        "dyn-print-sheet relative border border-slate-400 bg-white shadow-lg print:border-0 print:shadow-none",
        !isLast && "dyn-print-sheet-break mb-8 print:mb-0",
      )}
      style={{
        minHeight: paperHeight,
        padding: `${marginMm}mm`,
      }}
    >
      <div
        className="dyn-print-margin-guide pointer-events-none absolute border border-dashed border-sky-300/90 print:hidden"
        style={{ inset: `${marginMm}mm` }}
        aria-hidden
      />
      <div className="relative">
      <PrintLetterhead
        companyName={companyName}
        companyAddress={companyAddressBlock}
        department={print?.department ?? (isStatusReport ? record.entity.name : null)}
        status={!isStatusReport && print?.status_stamp !== false ? record.status : null}
      />

      <div
        className={cn(
          "mt-3 border-b border-slate-400 pb-2",
          print?.title_align === "center" || isAgreement || isStatusReport
            ? "text-center"
            : "text-left",
        )}
      >
        <h1 className="text-[1.35rem] font-bold uppercase tracking-wide text-slate-900">
          {documentTitle}
        </h1>
        {statusSubtitle ? <p className="mt-1 text-[12px] text-slate-700">{statusSubtitle}</p> : null}
        {record.title ? (
          <p className="mt-0.5 text-[12px] font-medium text-slate-800">{record.title}</p>
        ) : null}
      </div>

      {isStatusReport ? (
        <StatusReportBody
          record={record}
          print={print}
          relations={relations}
          siteBlock={siteBlock}
          vendorBlock={vendorBlock}
          lessorBlock={lessorBlock}
          utilityBlock={utilityBlock}
        />
      ) : (
        <div className="mt-4 space-y-4">
          {isAgreement && lessorBlock ? (
            <ContractPartiesSection
              index={1}
              title="Contract Parties"
              lesseeName={companyName}
              lessorBlock={lessorBlock}
            />
          ) : null}

          {groups.length > 0 ? (
            <>
              {groups.map((group, index) => {
                const sectionIndex = (isAgreement && lessorBlock ? 1 : 0) + index + 1;
                const groupFields = fields
                  .filter((f) => fieldGroupId(f) === group.id)
                  .sort((a, b) => a.field_order - b.field_order);

                if (/terms and conditions/i.test(group.name)) {
                  return (
                    <TermsSection
                      key={group.id}
                      index={sectionIndex}
                      title={group.name}
                      paragraphs={print?.terms_paragraphs ?? []}
                    />
                  );
                }

                if (/contract parties/i.test(group.name) && isAgreement) {
                  return null;
                }

                const relatedForGroup = relatedBlocks.find(
                  (b) =>
                    b.field !== "lessor_id" && b.title.toLowerCase() === group.name.toLowerCase(),
                );

                if (groupFields.length === 0 && !relatedForGroup) return null;

                return (
                  <PrintSection
                    key={group.id}
                    index={sectionIndex}
                    title={group.name}
                    layout={sectionLayout(group.name, groupFields, print?.layout)}
                    fields={groupFields}
                    values={record.values}
                    related={relatedForGroup}
                    print={print}
                    relations={relations}
                    hideRelationshipIds={
                      Boolean(relatedForGroup) ||
                      (isAgreement &&
                        groupFields.some((f) => f.name === "lessor_id" || f.name === "tower_site_id"))
                    }
                  />
                );
              })}
              {ungroupedFields.length > 0 ? (
                <PrintSection
                  index={(isAgreement && lessorBlock ? 1 : 0) + groups.length + 1}
                  title="Other fields"
                  layout="grid"
                  fields={ungroupedFields}
                  values={record.values}
                  print={print}
                  relations={relations}
                />
              ) : null}
            </>
          ) : (
            <PrintSection
              index={1}
              title="Details"
              layout="grid"
              fields={fields.sort((a, b) => a.field_order - b.field_order)}
              values={record.values}
              print={print}
              relations={relations}
            />
          )}

          {relatedBlocks
            .filter(
              (b) =>
                b.field !== "lessor_id" &&
                !groups.some((g) => g.name.toLowerCase() === b.title.toLowerCase()),
            )
            .map((block, i) => (
              <section key={block.title} className="break-inside-avoid">
                <SectionHeading
                  index={
                    (isAgreement && lessorBlock ? 1 : 0) +
                    groups.length +
                    (ungroupedFields.length > 0 ? 1 : 0) +
                    i +
                    1
                  }
                  title={block.title}
                />
                <RelatedGrid block={block} print={print} />
              </section>
            ))}

          {isAgreement &&
          (print?.terms_paragraphs?.length ?? 0) > 0 &&
          !groups.some((g) => /terms and conditions/i.test(g.name)) ? (
            <TermsSection
              index={
                (isAgreement && lessorBlock ? 1 : 0) +
                groups.length +
                relatedBlocks.filter((b) => b.field !== "lessor_id").length +
                1
              }
              title="Terms and Conditions"
              paragraphs={print?.terms_paragraphs ?? []}
            />
          ) : null}
        </div>
      )}

      {print?.signatures ? (
        print.signature_style === "parties" || isAgreement ? (
          <PartySignatureFooter lesseeName={companyName} lessorName={lessorName} />
        ) : (
          <PreparedSignatureFooter />
        )
      ) : null}
      </div>
    </article>
  );
}

function StatusReportBody({
  record,
  print,
  relations,
  siteBlock,
  vendorBlock,
  lessorBlock,
  utilityBlock,
}: {
  record: DynRecordDetail;
  print?: DynPrintSettings;
  relations: Record<string, DynResolvedRelation>;
  siteBlock?: RelatedBlock;
  vendorBlock?: RelatedBlock;
  lessorBlock?: RelatedBlock;
  utilityBlock?: RelatedBlock;
}) {
  const values = record.values;
  const fieldsByName = Object.fromEntries(record.entity.fields.map((f) => [f.name, f]));
  const stages = print?.workflow_stages ?? [];
  const milestoneFields = print?.milestone_fields ?? [];
  const remarksField = print?.remarks_field ?? null;
  const showSite = print?.show_site_assignment !== false;

  const siteName =
    firstNonEmpty(siteBlock?.values.site_name, siteBlock?.titleText, record.title) || "—";
  const region = firstNonEmpty(siteBlock?.values.region) || "—";
  const province = firstNonEmpty(siteBlock?.values.province) || "—";
  const town = firstNonEmpty(siteBlock?.values.town) || "—";
  const vendor =
    firstNonEmpty(
      vendorBlock?.values.name,
      vendorBlock?.values.vendor_name,
      vendorBlock?.values.company_name,
      vendorBlock?.titleText,
      relations.saq_vendor_id?.title,
      relations.power_vendor_id?.title,
      relations.cme_vendor_id?.title,
      relations.saq_contractor_id?.title,
    ) || "—";
  const lessor =
    firstNonEmpty(
      lessorBlock?.values.lessor_name,
      lessorBlock?.values.name,
      lessorBlock?.titleText,
      relations.lessor_id?.title,
    ) || "—";
  const utility =
    firstNonEmpty(
      utilityBlock?.values.name,
      utilityBlock?.values.utility_name,
      utilityBlock?.titleText,
      relations.electric_utility_id?.title,
    ) || null;

  let section = 0;

  return (
    <div className="mt-3 space-y-3.5">
      {showSite ? (
        <section className="break-inside-avoid">
          <SectionHeading index={++section} title="Site & Assignment" />
          <dl className="grid gap-x-5 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            <MetaCell label="Site Name" value={siteName} />
            <MetaCell label="Region" value={region} />
            <MetaCell label="Province" value={province} />
            <MetaCell label="Town" value={town} />
            {vendor !== "—" ? <MetaCell label="Vendor" value={vendor} /> : null}
            {lessor !== "—" ? <MetaCell label="Lessor" value={lessor} /> : null}
            {utility ? <MetaCell label="Utility" value={utility} /> : null}
          </dl>
        </section>
      ) : null}

      {milestoneFields.length > 0 ? (
        <section className="break-inside-avoid">
          <SectionHeading index={++section} title="Milestone Position" />
          <dl className="grid gap-x-5 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            {milestoneFields.map((name) => (
              <MetaCell
                key={name}
                label={fieldsByName[name]?.label ?? name}
                value={formatValue(values[name], name, print, relations)}
              />
            ))}
          </dl>
        </section>
      ) : null}

      {stages.length > 0 ? (
        <section className="break-inside-avoid">
          <SectionHeading index={++section} title="Acquisition Workflow" />
          <table className="w-full border-collapse text-[12px]">
            <thead>
              <tr className="border-b border-slate-400 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                <th className="py-1.5 pr-2">Stage</th>
                <th className="py-1.5 pr-2">Plan</th>
                <th className="py-1.5">Actual</th>
              </tr>
            </thead>
            <tbody>
              {stages.map((row) => (
                <tr key={row.stage} className="border-b border-slate-200">
                  <td className="py-1 pr-2 font-medium text-slate-800">{row.stage}</td>
                  <td className="py-1 pr-2 text-slate-900">
                    {row.plan ? formatValue(values[row.plan], row.plan, print, relations) : "—"}
                  </td>
                  <td className="py-1 text-slate-900">
                    {row.actual ? formatValue(values[row.actual], row.actual, print, relations) : "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      ) : null}

      {remarksField ? (
        <section className="break-inside-avoid">
          <SectionHeading index={++section} title="Remarks" />
          <div className="min-h-12 border border-slate-300 px-2.5 py-2 text-[12px] whitespace-pre-wrap text-slate-800">
            {formatValue(values[remarksField], remarksField, print, relations)}
          </div>
        </section>
      ) : null}
    </div>
  );
}

function MetaCell({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-[12px] leading-snug text-slate-900">{value}</dd>
    </div>
  );
}

function firstNonEmpty(...values: unknown[]): string | null {
  for (const v of values) {
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return null;
}

function PrintLetterhead({
  companyName,
  companyAddress,
  department,
  status,
}: {
  companyName: string;
  companyAddress?: string | null;
  department?: string | null;
  status: string | null;
}) {
  const approved = Boolean(status && /approv/i.test(status));
  const draft = Boolean(status && /draft/i.test(status));

  return (
    <header className="dyn-print-letterhead flex items-start justify-between gap-4">
      <div className="shrink-0">
        <TenantBrandMark size="md" className="print:shadow-none" />
      </div>
      <div className="dyn-print-letterhead-meta min-w-0 max-w-[70%] text-right">
        <p className="text-[13px] font-semibold tracking-tight text-slate-900">{companyName}</p>
        {companyAddress ? (
          <p className="mt-0.5 whitespace-pre-wrap text-[10px] leading-snug text-slate-600">
            {companyAddress}
          </p>
        ) : null}
        {department ? (
          <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-700">
            {department}
          </p>
        ) : null}
        {status ? (
          <div
            className={cn(
              "mt-1 inline-block rounded border px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide",
              approved
                ? "border-emerald-600 text-emerald-700"
                : draft
                  ? "border-amber-500 text-amber-700"
                  : "border-slate-500 text-slate-700",
            )}
          >
            {status}
          </div>
        ) : null}
      </div>
    </header>
  );
}

function ContractPartiesSection({
  index,
  title,
  lesseeName,
  lessorBlock,
}: {
  index: number;
  title: string;
  lesseeName: string;
  lessorBlock?: RelatedBlock;
}) {
  const lessorName =
    firstNonEmpty(lessorBlock?.values.lessor_name, lessorBlock?.titleText) || "—";
  const contact =
    firstNonEmpty(lessorBlock?.values.contact_number, lessorBlock?.values.email_address) || "—";

  return (
    <section className="break-inside-avoid">
      <SectionHeading index={index} title={title} />
      <dl className="grid gap-x-8 gap-y-3 sm:grid-cols-2">
        <div>
          <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-500">Lessee (Tenant)</dt>
          <dd className="mt-0.5 text-sm font-medium text-slate-900">{lesseeName}</dd>
        </div>
        <div>
          <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-500">Lessor (Landowner)</dt>
          <dd className="mt-0.5 text-sm font-medium text-slate-900">{lessorName}</dd>
        </div>
        <div>
          <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-500">Lessor Contact</dt>
          <dd className="mt-0.5 text-sm text-slate-900">{contact}</dd>
        </div>
      </dl>
    </section>
  );
}

function TermsSection({
  index,
  title,
  paragraphs,
}: {
  index: number;
  title: string;
  paragraphs: string[];
}) {
  return (
    <section className="break-inside-avoid">
      <SectionHeading index={index} title={title} />
      {paragraphs.length === 0 ? (
        <p className="text-sm text-slate-500">—</p>
      ) : (
        <ol className="list-decimal space-y-2 pl-5 text-sm leading-relaxed text-slate-800">
          {paragraphs.map((p) => (
            <li key={p.slice(0, 48)}>{p}</li>
          ))}
        </ol>
      )}
    </section>
  );
}

function sectionLayout(
  groupName: string,
  fields: DynField[],
  layout?: string,
): "grid" | "dates" | "remarks" {
  if (/remark/i.test(groupName) || (fields.length === 1 && fields[0]?.type === "textarea")) {
    return "remarks";
  }
  if (/processing\s*date|dates/i.test(groupName) && fields.every((f) => f.type === "date" || f.type === "datetime")) {
    return "dates";
  }
  if (layout === "transmittal" && /processing\s*date/i.test(groupName)) {
    return "dates";
  }
  return "grid";
}

function PrintSection({
  index,
  title,
  layout,
  fields,
  values,
  related,
  print,
  relations,
  hideRelationshipIds = false,
}: {
  index: number;
  title: string;
  layout: "grid" | "dates" | "remarks";
  fields: DynField[];
  values: Record<string, unknown>;
  related?: RelatedBlock;
  print?: DynPrintSettings;
  relations?: Record<string, DynResolvedRelation>;
  hideRelationshipIds?: boolean;
}) {
  const visibleFields = hideRelationshipIds
    ? fields.filter((f) => f.type !== "relationship")
    : fields;

  return (
    <section className="break-inside-avoid">
      <SectionHeading index={index} title={title} />
      {related ? <RelatedGrid block={related} print={print} /> : null}
      {!related && layout === "dates" ? (
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-b border-slate-300 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
              <th className="py-2 pr-3">Step</th>
              <th className="py-2">Date</th>
            </tr>
          </thead>
          <tbody>
            {visibleFields.map((field) => (
              <tr key={field.id} className="border-b border-slate-200">
                <td className="py-2 pr-3 text-slate-700">{field.label}</td>
                <td className="py-2 text-slate-900">
                  {formatValue(values[field.name], field.name, print, relations)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : null}
      {!related && layout === "remarks" ? (
        <div className="min-h-16 rounded-md border border-slate-200 bg-slate-50/60 p-3 text-sm whitespace-pre-wrap text-slate-800">
          {visibleFields.map((f) => formatValue(values[f.name], f.name, print, relations)).join("\n") || "—"}
        </div>
      ) : null}
      {!related && layout === "grid" ? (
        <FieldGrid fields={visibleFields} values={values} print={print} relations={relations} />
      ) : null}
      {related && visibleFields.length > 0 ? (
        <div className="mt-4">
          <FieldGrid fields={visibleFields} values={values} print={print} relations={relations} />
        </div>
      ) : null}
    </section>
  );
}

function FieldGrid({
  fields,
  values,
  print,
  relations,
}: {
  fields: DynField[];
  values: Record<string, unknown>;
  print?: DynPrintSettings;
  relations?: Record<string, DynResolvedRelation>;
}) {
  return (
    <dl className="dyn-print-field-grid grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
      {fields.map((field) => {
        const highlight = print?.highlight_fields?.includes(field.name);
        return (
          <div
            key={field.id}
            className={cn(field.column_span >= 12 ? "sm:col-span-2 lg:col-span-3 print:col-span-3" : undefined)}
          >
            <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-500">{field.label}</dt>
            <dd
              className={cn(
                "mt-0.5 text-sm text-slate-900",
                highlight && "font-semibold text-red-600",
              )}
            >
              {formatValue(values[field.name], field.name, print, relations)}
            </dd>
          </div>
        );
      })}
    </dl>
  );
}

function RelatedGrid({ block, print }: { block: RelatedBlock; print?: DynPrintSettings }) {
  return (
    <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
      {Object.entries(block.values).map(([key, value]) => (
        <div key={key}>
          <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-500">
            {block.labels[key] ?? key}
          </dt>
          <dd className="mt-0.5 text-sm text-slate-900">{formatValue(value, key, print)}</dd>
        </div>
      ))}
    </dl>
  );
}

function SectionHeading({ index, title }: { index: number; title: string }) {
  return (
    <h2 className="mb-1.5 border-b border-slate-300 pb-1 text-[12px] font-semibold tracking-tight text-slate-900">
      <span className="mr-1.5 text-slate-400">{index}.</span>
      {title.toUpperCase()}
    </h2>
  );
}

function PartySignatureFooter({ lesseeName, lessorName }: { lesseeName: string; lessorName: string }) {
  return (
    <footer className="mt-12 break-inside-avoid pt-2">
      <div className="grid gap-12 sm:grid-cols-2">
        <div>
          <p className="mb-10 text-xs font-medium text-slate-700">For the Lessee:</p>
          <div className="h-10 border-b border-slate-500" />
          <p className="mt-2 text-sm font-medium text-slate-900">{lesseeName}</p>
          <p className="text-xs text-slate-600">Authorized Representative</p>
        </div>
        <div>
          <p className="mb-10 text-xs font-medium text-slate-700">For the Lessor:</p>
          <div className="h-10 border-b border-slate-500" />
          <p className="mt-2 text-sm font-medium text-slate-900">{lessorName}</p>
          <p className="text-xs text-slate-600">Landowner / Lessor</p>
        </div>
      </div>
    </footer>
  );
}

function PreparedSignatureFooter() {
  return (
    <footer className="mt-8 break-inside-avoid pt-2">
      <div className="grid gap-8 sm:grid-cols-2">
        <div>
          <div className="h-8 border-b border-slate-500" />
          <p className="mt-1.5 text-[11px] font-medium text-slate-700">Prepared by</p>
        </div>
        <div>
          <div className="h-8 border-b border-slate-500" />
          <p className="mt-1.5 text-[11px] font-medium text-slate-700">Approved by</p>
        </div>
      </div>
    </footer>
  );
}

function formatValue(
  value: unknown,
  fieldName?: string,
  print?: DynPrintSettings,
  relations?: Record<string, DynResolvedRelation>,
): string {
  if (fieldName && relations?.[fieldName]?.title) {
    return relations[fieldName].title!;
  }
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "string" && /^[0-9a-f-]{36}$/i.test(value) && fieldName && relations?.[fieldName]) {
    return relations[fieldName].title || "—";
  }
  if (typeof value === "number") {
    const asCurrency = fieldName && print?.currency_fields?.includes(fieldName);
    if (asCurrency) {
      return `₱ ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    return Number.isInteger(value)
      ? value.toLocaleString()
      : value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  if (typeof value === "string" && fieldName && print?.currency_fields?.includes(fieldName)) {
    const n = Number(value);
    if (!Number.isNaN(n)) {
      return `₱ ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
  }
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}
