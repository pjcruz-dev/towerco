"use client";

import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";
import { resolvePrintAssetUrl } from "@/modules/e-approval/print-utils";

type Props = {
  data: EApprovalPrintPayload;
  template: EApprovalPrintTemplate;
  title: string;
};

export function ProcurementPrintHeader({ data, template, title }: Props) {
  const header = template.header ?? {};
  const logoUrl = header.showLogo !== false ? resolvePrintAssetUrl(data.brand_logo_url) : null;

  return (
    <header className="border-b border-slate-300 pb-4">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt="" className="mb-2 h-12 max-w-[200px] object-contain" />
          ) : null}
          <p className="text-xs text-slate-600">{data.form_name}</p>
          {header.showRequestor !== false && data.requestor ? (
            <p className="mt-1 text-sm text-slate-700">
              <span className="text-slate-500">Requestor · </span>
              {data.requestor}
            </p>
          ) : null}
        </div>
        <div className="text-right">
          <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{header.title ?? title}</h1>
          {header.showStatus !== false ? (
            <p className="mt-1 text-sm text-slate-600">
              <span className="font-medium text-slate-800">{data.status}</span>
            </p>
          ) : null}
          {header.showDocumentNo !== false ? (
            <p className="text-sm font-medium text-slate-900">{data.document_no}</p>
          ) : null}
          {header.showDate !== false ? (
            <p className="text-xs text-slate-500">{data.created_at ?? "—"}</p>
          ) : null}
        </div>
      </div>
    </header>
  );
}

export function ProcurementPrintPageStyles(props?: {
  size?: string;
  marginMm?: number;
  orientation?: "portrait" | "landscape";
}) {
  const size = (props?.size ?? "A4").trim() || "A4";
  const orientation = props?.orientation === "landscape" ? "landscape" : "portrait";
  const margin = Math.min(40, Math.max(0, Number(props?.marginMm ?? 10) || 0));

  return (
    <style
      dangerouslySetInnerHTML={{
        __html: `@media print {
            @page { size: ${size} ${orientation}; margin: ${margin}mm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .eapproval-procurement-print { background: #fff !important; }
            .eapproval-procurement-print-footer { break-inside: avoid; }
            .eapproval-generic-form-print { background: #fff !important; }
          }`,
      }}
    />
  );
}
