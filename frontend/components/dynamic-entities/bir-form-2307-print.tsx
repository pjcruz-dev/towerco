"use client";

import { useEffect, useMemo, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import type { DynRecordDetail } from "@/lib/api/modules/dynamic-entities-api";
import {
  BIR_2307_FIELDS,
  BIR_2307_PAGE,
  buildBir2307Values,
  type Bir2307OverlayField,
} from "@/lib/dynamic-entities/bir-2307-field-map";
import { fillBir2307OfficialPdf } from "@/lib/dynamic-entities/bir-2307-fill-pdf";

/**
 * Exact BIR Form 2307 (Jan 2018 ENCS) print — official form artwork + field overlays.
 * Does not redraw a custom form.
 */
export function BirForm2307Print({ record }: { record: DynRecordDetail }) {
  const values = useMemo(
    () => buildBir2307Values(record.values, record.title),
    [record.values, record.title],
  );
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const objectUrlRef = useRef<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setBusy(true);
    setError(null);
    fillBir2307OfficialPdf(values)
      .then((bytes) => {
        if (cancelled) return;
        if (objectUrlRef.current) URL.revokeObjectURL(objectUrlRef.current);
        const blob = new Blob([bytes], { type: "application/pdf" });
        const url = URL.createObjectURL(blob);
        objectUrlRef.current = url;
        setPdfUrl(url);
      })
      .catch((e: unknown) => {
        if (!cancelled) setError(e instanceof Error ? e.message : "Unable to fill BIR 2307 PDF.");
      })
      .finally(() => {
        if (!cancelled) setBusy(false);
      });
    return () => {
      cancelled = true;
      if (objectUrlRef.current) {
        URL.revokeObjectURL(objectUrlRef.current);
        objectUrlRef.current = null;
      }
    };
  }, [values]);

  function downloadPdf() {
    if (!pdfUrl) return;
    const a = document.createElement("a");
    a.href = pdfUrl;
    a.download = `BIR-2307-${values.control_number || record.id.slice(0, 8)}.pdf`;
    a.click();
  }

  function printPdf() {
    if (!pdfUrl) return;
    const w = window.open(pdfUrl, "_blank", "noopener,noreferrer");
    if (!w) {
      // Popup blocked — fall back to iframe print of overlay sheet
      window.print();
      return;
    }
    const tryPrint = () => {
      try {
        w.focus();
        w.print();
      } catch {
        /* browser may require user gesture after load */
      }
    };
    w.addEventListener("load", () => setTimeout(tryPrint, 400));
    setTimeout(tryPrint, 1200);
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-2 print:hidden">
        <Button type="button" size="sm" onClick={printPdf} disabled={!pdfUrl || busy}>
          Print official BIR 2307
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={downloadPdf} disabled={!pdfUrl || busy}>
          Download filled PDF
        </Button>
        <a
          className="text-xs text-muted-foreground underline-offset-2 hover:underline"
          href={BIR_2307_PAGE.templatePdfSrc}
          target="_blank"
          rel="noreferrer"
        >
          Blank official form
        </a>
        {busy ? <span className="text-xs text-muted-foreground">Filling official PDF…</span> : null}
        {error ? <span className="text-xs text-destructive">{error}</span> : null}
      </div>

      {/* Screen preview: filled official PDF */}
      {pdfUrl ? (
        <iframe
          title="BIR Form 2307"
          src={pdfUrl}
          className="h-[min(92vh,980px)] w-full rounded-md border border-border bg-white print:hidden"
        />
      ) : (
        <p className="print:hidden text-sm text-muted-foreground">Preparing official BIR Form 2307…</p>
      )}

      {/* Browser-print fallback: official page-1 artwork + overlays (exact form, not a redesign) */}
      <div className="hidden print:block">
        <OfficialFormOverlay values={values} />
      </div>
    </div>
  );
}

function OfficialFormOverlay({ values }: { values: Record<string, string> }) {
  return (
    <div
      className="bir2307-exact relative mx-auto overflow-hidden bg-white"
      style={{
        width: BIR_2307_PAGE.width,
        height: BIR_2307_PAGE.height,
      }}
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={BIR_2307_PAGE.backgroundSrc}
        alt="BIR Form 2307 January 2018 ENCS"
        className="pointer-events-none absolute inset-0 h-full w-full select-none object-fill"
        draggable={false}
      />
      {BIR_2307_FIELDS.map((field) => (
        <OverlayField key={field.id} field={field} value={values[field.id] ?? ""} />
      ))}
    </div>
  );
}

function OverlayField({ field, value }: { field: Bir2307OverlayField; value: string }) {
  if (!value) return null;

  if (field.kind === "chars") {
    const chars = [...value];
    let x = field.x;
    return (
      <>
        {chars.map((ch, i) => {
          if (i > 0 && field.gapsAfter?.includes(i)) x += field.gapExtra ?? 0;
          const left = x;
          x += field.pitch;
          return (
            <span
              key={`${field.id}-${i}`}
              className="absolute font-sans font-semibold text-black"
              style={{
                left,
                top: field.y,
                fontSize: field.size ?? 10,
                lineHeight: 1,
                width: field.pitch,
                textAlign: "center",
              }}
            >
              {ch}
            </span>
          );
        })}
      </>
    );
  }

  const text = field.uppercase ? value.toUpperCase() : value;
  return (
    <span
      className="absolute overflow-hidden font-sans text-black"
      style={{
        left: field.x,
        top: field.y,
        width: field.w,
        fontSize: field.size ?? 9,
        fontWeight: field.bold ? 600 : 400,
        textAlign: field.align ?? "left",
        lineHeight: 1.1,
        whiteSpace: "nowrap",
        textOverflow: "ellipsis",
      }}
    >
      {text}
    </span>
  );
}
