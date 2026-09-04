"use client";

import { useDeferredValue, useEffect, useMemo, useRef, useState } from "react";
import { Minus, Plus, RotateCcw } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  buildEApprovalDocumentDesignPreviewPayload,
  defaultEApprovalDocumentDesignCss,
  defaultEApprovalDocumentDesignHtml,
  documentDesignPreviewRecommendations,
  EAPPROVAL_SYSTEM_PRINT_TOKENS,
  printableDesignFields,
  renderEApprovalPrintTemplateHtml,
  type DocumentDesignFieldRef,
} from "@/lib/e-approval/e-approval-print-template-render";
import { hydrateEApprovalPrintLogoUrls } from "@/lib/e-approval/fetch-authenticated-asset";
import { cn } from "@/lib/utils";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type DesignMode = "design" | "styles" | "source";

type Props = {
  formTitle?: string;
  fields: DocumentDesignFieldRef[];
  fieldTokens: Array<{ token: string; label: string }>;
  html: string;
  css: string;
  pageSize?: string;
  subsidiaryLogos?: Record<string, string>;
  subsidiaryLogoField?: string;
  onHtmlChange: (html: string) => void;
  onCssChange: (css: string) => void;
};

const ZOOM_MIN = 50;
const ZOOM_MAX = 150;
const ZOOM_STEP = 10;

function paperWidthMm(pageSize: string | undefined): number {
  switch ((pageSize ?? "A4").toLowerCase()) {
    case "letter":
      return 216;
    case "legal":
      return 216;
    default:
      return 210;
  }
}

function paperHeightMm(pageSize: string | undefined): number {
  switch ((pageSize ?? "A4").toLowerCase()) {
    case "letter":
      return 279;
    case "legal":
      return 356;
    default:
      return 297;
  }
}

export function EApprovalDocumentDesignEditor({
  formTitle,
  fields,
  fieldTokens,
  html,
  css,
  pageSize = "A4",
  subsidiaryLogos,
  subsidiaryLogoField,
  onHtmlChange,
  onCssChange,
}: Props) {
  const [mode, setMode] = useState<DesignMode>("design");
  const [zoom, setZoom] = useState(75);
  const designRef = useRef<HTMLDivElement>(null);
  const deferredHtml = useDeferredValue(html);
  const deferredCss = useDeferredValue(css);
  const bodyFields = useMemo(() => printableDesignFields(fields), [fields]);

  useEffect(() => {
    if (mode !== "design") return;
    const el = designRef.current;
    if (!el) return;
    if (el.innerHTML !== html) {
      el.innerHTML = html || "";
    }
  }, [html, mode]);

  const previewPayload = useMemo(
    () =>
      buildEApprovalDocumentDesignPreviewPayload(fields, formTitle, {
        subsidiary_logos: subsidiaryLogos,
        subsidiary_logo_field: subsidiaryLogoField,
      }),
    [fields, formTitle, subsidiaryLogoField, subsidiaryLogos],
  );

  const [hydratedPreviewPayload, setHydratedPreviewPayload] = useState<EApprovalPrintPayload | null>(null);

  useEffect(() => {
    let cancelled = false;
    void hydrateEApprovalPrintLogoUrls(previewPayload).then((next) => {
      if (!cancelled) {
        setHydratedPreviewPayload(next);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [previewPayload]);

  const previewHtml = useMemo(
    () =>
      renderEApprovalPrintTemplateHtml(
        deferredHtml,
        hydratedPreviewPayload ?? previewPayload,
      ),
    [deferredHtml, hydratedPreviewPayload, previewPayload],
  );

  const recommendations = useMemo(
    () => documentDesignPreviewRecommendations(deferredHtml, deferredCss, bodyFields.length),
    [bodyFields.length, deferredCss, deferredHtml],
  );

  function insertToken(token: string) {
    if (mode === "styles") {
      onCssChange(`${css}${css.endsWith("\n") || css === "" ? "" : "\n"}/* ${token} */`);
      return;
    }

    if (mode === "source") {
      onHtmlChange(`${html}${token}`);
      return;
    }

    const el = designRef.current;
    if (!el) {
      onHtmlChange(`${html}${token}`);
      return;
    }
    el.focus();
    const selection = window.getSelection();
    if (selection && selection.rangeCount > 0 && el.contains(selection.anchorNode)) {
      const range = selection.getRangeAt(0);
      range.deleteContents();
      const textNode = document.createTextNode(token);
      range.insertNode(textNode);
      range.setStartAfter(textNode);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      el.appendChild(document.createTextNode(token));
    }
    onHtmlChange(el.innerHTML);
  }

  function seedDefaults() {
    onHtmlChange(defaultEApprovalDocumentDesignHtml(formTitle, fields));
    onCssChange(defaultEApprovalDocumentDesignCss());
    setMode("design");
    setZoom(75);
  }

  const widthMm = paperWidthMm(pageSize);
  const heightMm = paperHeightMm(pageSize);
  const scale = zoom / 100;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <h3 className="text-sm font-medium text-foreground">Document design</h3>
          <p className="mt-1 text-xs text-muted-foreground">
            Form-style print body for this form. Insert starter layout builds letterhead + field table from your
            current fields. Workflow approval signatures are stamped dynamically under the form from the
            submission (not as static boxes in the layout).
          </p>
        </div>
        <Button type="button" size="sm" onClick={seedDefaults}>
          Insert starter layout
        </Button>
      </div>

      <div className="flex flex-wrap gap-1">
        {(
          [
            ["design", "Design"],
            ["styles", "Styles"],
            ["source", "Source"],
          ] as const
        ).map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={cn(
              "rounded-md px-2.5 py-1.5 text-xs font-medium",
              mode === id ? "bg-primary text-primary-foreground" : "bg-muted/60 text-muted-foreground hover:bg-muted",
            )}
            onClick={() => setMode(id)}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(15rem,17rem)_minmax(22rem,1.15fr)]">
        <div className="min-w-0 space-y-2">
          {mode === "design" ? (
            <div
              ref={designRef}
              contentEditable
              suppressContentEditableWarning
              className="min-h-[320px] rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
              onInput={(e) => onHtmlChange((e.target as HTMLDivElement).innerHTML)}
              onBlur={(e) => onHtmlChange((e.target as HTMLDivElement).innerHTML)}
            />
          ) : null}
          {mode === "styles" ? (
            <div className="space-y-2">
              <Label htmlFor="ea-print-css">CSS</Label>
              <Textarea
                id="ea-print-css"
                value={css}
                onChange={(e) => onCssChange(e.target.value)}
                rows={18}
                className="font-mono text-xs"
              />
            </div>
          ) : null}
          {mode === "source" ? (
            <div className="space-y-2">
              <Label htmlFor="ea-print-html">HTML</Label>
              <Textarea
                id="ea-print-html"
                value={html}
                onChange={(e) => onHtmlChange(e.target.value)}
                rows={18}
                className="font-mono text-xs"
              />
            </div>
          ) : null}
        </div>

        <aside className="rounded-xl border border-border bg-card p-2 shadow-sm">
          <p className="px-1 text-xs font-medium text-foreground">Insert token</p>
          <p className="mt-0.5 px-1 text-[11px] text-muted-foreground">
            {bodyFields.length} printable field{bodyFields.length === 1 ? "" : "s"}
          </p>
          <ul className="mt-2 max-h-[32rem] space-y-1 overflow-y-auto">
            <li className="px-1 pt-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              System
            </li>
            {EAPPROVAL_SYSTEM_PRINT_TOKENS.map((item) => (
              <li key={item.token}>
                <button
                  type="button"
                  className="w-full rounded-md px-2 py-1.5 text-left text-xs hover:bg-muted"
                  onClick={() => insertToken(item.token)}
                >
                  <span className="block font-medium text-foreground">{item.label}</span>
                  <span className="block truncate font-mono text-[10px] text-muted-foreground">{item.token}</span>
                </button>
              </li>
            ))}
            <li className="px-1 pt-2 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Form fields
            </li>
            {fieldTokens.length === 0 ? (
              <li className="px-2 py-2 text-xs text-muted-foreground">No fields yet.</li>
            ) : (
              fieldTokens.map((item) => (
                <li key={item.token}>
                  <button
                    type="button"
                    className="w-full rounded-md px-2 py-1.5 text-left text-xs hover:bg-muted"
                    onClick={() => insertToken(item.token)}
                  >
                    <span className="block font-medium text-foreground">{item.label}</span>
                    <span className="block truncate font-mono text-[10px] text-muted-foreground">{item.token}</span>
                  </button>
                </li>
              ))
            )}
          </ul>
        </aside>

        <aside className="flex min-h-[32rem] flex-col overflow-hidden rounded-xl border border-border bg-slate-100 shadow-sm dark:bg-slate-900/40">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-card px-3 py-2">
            <div>
              <p className="text-xs font-medium text-foreground">Live preview</p>
              <p className="text-[11px] text-muted-foreground">
                {pageSize} · sample data · updates live
              </p>
            </div>
            <div className="flex items-center gap-1">
              <Button
                type="button"
                size="icon-xs"
                variant="outline"
                aria-label="Zoom out"
                disabled={zoom <= ZOOM_MIN}
                onClick={() => setZoom((z) => Math.max(ZOOM_MIN, z - ZOOM_STEP))}
              >
                <Minus className="size-3.5" />
              </Button>
              <span className="min-w-[3rem] text-center text-xs tabular-nums text-muted-foreground">{zoom}%</span>
              <Button
                type="button"
                size="icon-xs"
                variant="outline"
                aria-label="Zoom in"
                disabled={zoom >= ZOOM_MAX}
                onClick={() => setZoom((z) => Math.min(ZOOM_MAX, z + ZOOM_STEP))}
              >
                <Plus className="size-3.5" />
              </Button>
              <Button
                type="button"
                size="icon-xs"
                variant="ghost"
                aria-label="Reset zoom"
                onClick={() => setZoom(75)}
              >
                <RotateCcw className="size-3.5" />
              </Button>
            </div>
          </div>

          <div className="flex-1 overflow-auto p-4">
            <div
              className="mx-auto"
              style={{
                width: `${widthMm * scale}mm`,
                minHeight: `${heightMm * scale}mm`,
              }}
            >
              <div
                className="bg-white shadow-md ring-1 ring-slate-200"
                style={{
                  width: `${widthMm}mm`,
                  minHeight: `${heightMm}mm`,
                  transform: `scale(${scale})`,
                  transformOrigin: "top left",
                }}
              >
                {deferredCss ? <style dangerouslySetInnerHTML={{ __html: deferredCss }} /> : null}
                {previewHtml ? (
                  <div
                    className="eapproval-document-design-preview p-5"
                    dangerouslySetInnerHTML={{ __html: previewHtml }}
                  />
                ) : (
                  <div className="flex min-h-[240px] flex-col items-center justify-center gap-3 p-8 text-center">
                    <p className="text-sm font-medium text-slate-700">No print layout yet</p>
                    <p className="max-w-xs text-xs text-slate-500">
                      Insert starter layout to generate a form-style document with letterhead and all current fields.
                    </p>
                    <Button type="button" size="sm" onClick={seedDefaults}>
                      Insert starter layout
                    </Button>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="border-t border-border bg-card px-3 py-2.5">
            <p className="text-xs font-medium text-foreground">Recommendations</p>
            <ul className="mt-1.5 list-disc space-y-1 pl-4 text-[11px] leading-relaxed text-muted-foreground">
              {recommendations.map((tip) => (
                <li key={tip}>{tip}</li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
