"use client";

import Link from "next/link";
import { useLayoutEffect, useRef, useState, type ReactNode } from "react";
import { Minus, Pencil, Plus, Printer } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { cn } from "@/lib/utils";

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
  "h-8 !w-auto min-w-[7.5rem] max-w-[11rem] shrink-0 rounded-md border border-slate-600 bg-slate-800 px-2.5 text-xs font-medium text-slate-100 outline-none hover:border-slate-500 focus:border-sky-500";

export function PrintDocumentFrame({
  backHref,
  editTemplateHref,
  listLabel,
  children,
  defaultPaper = "a4",
}: {
  backHref: string;
  editTemplateHref?: string | null;
  listLabel: string;
  children: ReactNode;
  defaultPaper?: PaperSize;
}) {
  const [paper, setPaper] = useState<PaperSize>(defaultPaper);
  const [orientation, setOrientation] = useState<Orientation>("portrait");
  const [margin, setMargin] = useState<MarginPreset>("default");
  const [zoomMode, setZoomMode] = useState<ZoomMode>("fit-page");
  const [previewScale, setPreviewScale] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const stageRef = useRef<HTMLDivElement | null>(null);
  const sheetRef = useRef<HTMLElement | null>(null);

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
      const pagePx = Math.max(1, heightMm * (96 / 25.4));
      setPageCount(Math.max(1, Math.ceil(sheet.scrollHeight / pagePx - 0.02)));
    }
    recompute();
    const observer = typeof ResizeObserver !== "undefined" ? new ResizeObserver(() => recompute()) : null;
    if (stageRef.current) observer?.observe(stageRef.current);
    if (sheetRef.current) observer?.observe(sheetRef.current);
    window.addEventListener("resize", recompute);
    return () => {
      observer?.disconnect();
      window.removeEventListener("resize", recompute);
    };
  }, [zoomMode, paper, orientation, margin, heightMm, children]);

  function nudgeZoom(dir: -1 | 1) {
    if (zoomMode === "fit-page" || zoomMode === "fit-width") {
      setZoomMode(100);
      return;
    }
    const index = ZOOM_STEPS.indexOf(zoomMode);
    setZoomMode(ZOOM_STEPS[Math.max(0, Math.min(ZOOM_STEPS.length - 1, index + dir))]!);
  }

  return (
    <div className="dyn-print-root min-h-screen bg-[#404040] print:min-h-0 print:!bg-white">
      <header className="dyn-print-toolbar sticky top-0 z-10 border-b border-black/40 bg-[#1e1e1e] text-neutral-50 print:hidden">
        <div className="flex flex-wrap items-center gap-2 px-3 py-2 sm:px-4">
          <Link
            href={backHref}
            className="mr-1 shrink-0 text-xs text-slate-400 underline-offset-2 hover:text-white hover:underline"
          >
            Back
          </Link>
          {editTemplateHref ? (
            <Link
              href={editTemplateHref}
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-600 bg-slate-800 px-2.5 text-xs font-medium text-slate-100 hover:border-slate-500"
            >
              <Pencil className="size-3.5" />
              Edit Template
            </Link>
          ) : null}
          <Select
            className={toolbarSelectClass}
            value={paper}
            onChange={(event) => setPaper(event.target.value as PaperSize)}
            aria-label="Paper size"
          >
            {(Object.keys(PAPER_MM) as PaperSize[]).map((key) => (
              <option key={key} value={key}>
                {PAPER_MM[key].label}
              </option>
            ))}
          </Select>
          <Select
            className={toolbarSelectClass}
            value={orientation}
            onChange={(event) => setOrientation(event.target.value as Orientation)}
            aria-label="Orientation"
          >
            <option value="portrait">Portrait</option>
            <option value="landscape">Landscape</option>
          </Select>
          <Select
            className={toolbarSelectClass}
            value={margin}
            onChange={(event) => setMargin(event.target.value as MarginPreset)}
            aria-label="Margins"
          >
            <option value="default">Default · 12mm</option>
            <option value="narrow">Narrow · 6mm</option>
            <option value="wide">Wide · 20mm</option>
            <option value="none">None</option>
          </Select>
          <div className="flex items-center gap-0.5">
            <button
              type="button"
              className="flex size-8 items-center justify-center rounded-md border border-slate-600 bg-slate-800 text-slate-100 hover:border-slate-500"
              onClick={() => nudgeZoom(-1)}
              aria-label="Zoom out"
            >
              <Minus className="size-3.5" />
            </button>
            <Select
              className={cn(toolbarSelectClass, "min-w-[6.5rem]")}
              value={zoomMode}
              onChange={(event) => {
                const value = event.target.value;
                if (value === "fit-page" || value === "fit-width") setZoomMode(value);
                else setZoomMode(Number(value) as ZoomMode);
              }}
              aria-label="Zoom"
            >
              <option value="fit-width">Fit width</option>
              <option value="fit-page">Fit page</option>
              {ZOOM_STEPS.map((step) => (
                <option key={step} value={step}>
                  {step}%
                </option>
              ))}
            </Select>
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
            {pageCount} {pageCount === 1 ? "page" : "pages"}
          </span>
          <div className="ml-auto flex items-center gap-2">
            <p className="hidden max-w-[14rem] truncate text-xs text-slate-400 lg:block">{listLabel}</p>
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
        <div className="flex flex-col items-center gap-3">
          <div
            className="dyn-print-scale origin-top print:!m-0 print:!w-full print:!max-w-none print:!transform-none"
            style={{ width: paperWidth, transform: `scale(${previewScale})` }}
          >
            <article
              ref={sheetRef}
              className="dyn-print-sheet relative border border-slate-400 bg-white shadow-lg print:border-0 print:shadow-none"
              style={{ minHeight: paperHeight, padding: `${marginMm}mm` }}
            >
              <div
                className="dyn-print-margin-guide pointer-events-none absolute border border-dashed border-sky-300/90 print:hidden"
                style={{ inset: `${marginMm}mm` }}
                aria-hidden
              />
              <div className="dyn-print-template relative">{children}</div>
            </article>
          </div>
          <p className="max-w-xl text-center text-[11px] text-slate-400 print:hidden">
            Preview scaled for screen · Print uses real paper size · {PAPER_MM[paper].label} {orientation} · {widthMm} × {heightMm} mm · margins {marginMm} mm
          </p>
        </div>
      </div>
      <style
        dangerouslySetInnerHTML={{
          __html: `
            @media print {
              @page { size: ${pageSizeCss}; margin: ${marginMm}mm; }
              html, body { background: #fff !important; }
              .dyn-print-toolbar { display: none !important; }
              .dyn-print-root, .dyn-print-stage {
                background: #fff !important;
                min-height: 0 !important;
                height: auto !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
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
              }
            }
          `,
        }}
      />
    </div>
  );
}
