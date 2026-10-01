"use client";

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from "react";

import type { DynRecordDetail } from "@/lib/api/modules/dynamic-entities-api";
import {
  BIR_2307_FIELDS,
  BIR_2307_PAGE,
  buildBir2307Values,
  type Bir2307OverlayField,
} from "@/lib/dynamic-entities/bir-2307-field-map";
import { fillBir2307OfficialPdf } from "@/lib/dynamic-entities/bir-2307-fill-pdf";

export type BirForm2307PrintHandle = {
  print: () => void;
  download: () => void;
  busy: boolean;
  ready: boolean;
  error: string | null;
};

const PREVIEW_SCALE = 2.5;

/**
 * Screen: sharp canvas render of the filled official PDF (paper-sheet UI, no PDF chrome).
 * Print/Download: vector PDF via imperative handle.
 */
export const BirForm2307Print = forwardRef<BirForm2307PrintHandle, { record: DynRecordDetail }>(
  function BirForm2307Print({ record }, ref) {
    const values = useMemo(
      () => buildBir2307Values(record.values, record.title),
      [record.values, record.title],
    );
    const [pdfUrl, setPdfUrl] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const objectUrlRef = useRef<string | null>(null);
    const previewUrlRef = useRef<string | null>(null);

    useEffect(() => {
      let cancelled = false;
      setBusy(true);
      setError(null);
      setPreviewUrl(null);

      (async () => {
        try {
          const bytes = await fillBir2307OfficialPdf(values);
          if (cancelled) return;

          if (objectUrlRef.current) URL.revokeObjectURL(objectUrlRef.current);
          const blob = new Blob([bytes], { type: "application/pdf" });
          const url = URL.createObjectURL(blob);
          objectUrlRef.current = url;
          setPdfUrl(url);

          const raster = await rasterizePdfPage(bytes, PREVIEW_SCALE);
          if (cancelled) {
            if (raster) URL.revokeObjectURL(raster);
            return;
          }
          if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
          previewUrlRef.current = raster;
          setPreviewUrl(raster);
        } catch (e: unknown) {
          if (!cancelled) {
            setError(e instanceof Error ? e.message : "Unable to fill BIR 2307 PDF.");
          }
        } finally {
          if (!cancelled) setBusy(false);
        }
      })();

      return () => {
        cancelled = true;
        if (objectUrlRef.current) {
          URL.revokeObjectURL(objectUrlRef.current);
          objectUrlRef.current = null;
        }
        if (previewUrlRef.current) {
          URL.revokeObjectURL(previewUrlRef.current);
          previewUrlRef.current = null;
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
      if (!pdfUrl) {
        window.print();
        return;
      }
      const w = window.open(pdfUrl, "_blank", "noopener,noreferrer");
      if (!w) {
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

    useImperativeHandle(
      ref,
      () => ({
        print: printPdf,
        download: downloadPdf,
        busy,
        ready: Boolean(pdfUrl) && !busy,
        error,
      }),
      [pdfUrl, busy, error, values.control_number, record.id],
    );

    return (
      <>
        <div className="print:hidden">
          {previewUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={previewUrl}
              alt="BIR Form 2307 preview"
              className="mx-auto block bg-white"
              style={{
                width: BIR_2307_PAGE.width,
                height: BIR_2307_PAGE.height,
              }}
              draggable={false}
            />
          ) : (
            <>
              <OfficialFormOverlay values={values} />
              {busy ? (
                <p className="px-3 py-2 text-center text-[11px] text-muted-foreground">
                  Rendering sharp preview…
                </p>
              ) : null}
            </>
          )}
          {error ? (
            <p className="px-3 py-2 text-center text-[11px] text-destructive">{error}</p>
          ) : null}
        </div>

        {/* Popup-blocked browser-print fallback */}
        <div className="hidden print:block">
          <OfficialFormOverlay values={values} />
        </div>
      </>
    );
  },
);

async function rasterizePdfPage(pdfBytes: Uint8Array, scale: number): Promise<string | null> {
  try {
    const pdfjs = await import("pdfjs-dist");
    pdfjs.GlobalWorkerOptions.workerSrc = "/pdfjs/pdf.worker.min.mjs";

    // pdf.js transfers/detaches the buffer — always pass a fresh copy.
    const copy = Uint8Array.from(pdfBytes);
    const doc = await pdfjs.getDocument({ data: copy }).promise;
    const page = await doc.getPage(1);
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement("canvas");
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      await doc.destroy();
      return null;
    }

    await page.render({
      canvasContext: ctx,
      viewport,
      canvas,
    }).promise;
    await doc.destroy();

    return await new Promise<string | null>((resolve) => {
      canvas.toBlob((blob) => {
        resolve(blob ? URL.createObjectURL(blob) : null);
      }, "image/png");
    });
  } catch {
    return null;
  }
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
    if (field.xs && field.xs.length > 0) {
      return (
        <>
          {chars.map((ch, i) => {
            const left = field.xs![i];
            if (left == null) return null;
            const cellW = (field.xs![i + 1] != null ? field.xs![i + 1]! - left : field.pitch) || field.pitch;
            return (
              <span
                key={`${field.id}-${i}`}
                className="absolute font-sans font-semibold text-black"
                style={{
                  left,
                  top: field.y,
                  fontSize: field.size ?? 10,
                  lineHeight: 1,
                  width: cellW,
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
