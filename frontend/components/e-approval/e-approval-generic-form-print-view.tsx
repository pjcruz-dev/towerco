"use client";

import { useEffect, useMemo, useState } from "react";

import { ApprovalHistoryPrintBlock } from "@/components/e-approval/print/approval-history-print-block";
import { ProcurementPrintPageStyles } from "@/components/e-approval/print/procurement-print-shell";
import {
  defaultEApprovalDocumentDesignCss,
  defaultEApprovalDocumentDesignHtml,
  documentDesignEmbedsGrids,
  hasCustomPrintDocumentDesign,
  renderEApprovalPrintTemplateHtml,
} from "@/lib/e-approval/e-approval-print-template-render";
import { hydrateEApprovalPrintLogoUrls } from "@/lib/e-approval/fetch-authenticated-asset";
import {
  buildApprovalHistorySlots,
  resolvePrintTemplate,
  shouldShowApprovalHistory,
} from "@/modules/e-approval/approval-history-print";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type Props = {
  data: EApprovalPrintPayload;
  showApprovalFooter?: boolean;
  fieldsDataHelp?: string;
  /** @deprecated Approval trail table removed from print; kept for call-site compatibility. */
  trailDataHelp?: string;
  footerDataHelp?: string;
};

export function EApprovalGenericFormPrintView({
  data,
  showApprovalFooter = true,
  fieldsDataHelp,
  footerDataHelp,
}: Props) {
  const [hydratedData, setHydratedData] = useState<EApprovalPrintPayload>(data);

  useEffect(() => {
    let cancelled = false;
    setHydratedData(data);
    void hydrateEApprovalPrintLogoUrls(data).then((next) => {
      if (!cancelled) {
        setHydratedData(next);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [data]);

  const template = resolvePrintTemplate(hydratedData);
  const approvalSlots = useMemo(
    () => buildApprovalHistorySlots(hydratedData, template),
    [hydratedData, template],
  );
  const unattachedFiles = useMemo(
    () =>
      (hydratedData.attachments ?? []).filter(
        (attachment) =>
          !attachment.field_name ||
          !hydratedData.fields.some((field) => field.key === attachment.field_name),
      ),
    [hydratedData.attachments, hydratedData.fields],
  );

  const title = template.header?.title?.trim() || hydratedData.form_name?.trim() || "Submission";

  const sourceHtml = useMemo(() => {
    if (hasCustomPrintDocumentDesign(template)) {
      return String(template.template_html ?? "");
    }
    return defaultEApprovalDocumentDesignHtml(title);
  }, [template, title]);

  const documentHtml = useMemo(
    () => renderEApprovalPrintTemplateHtml(sourceHtml, hydratedData),
    [hydratedData, sourceHtml],
  );

  const gridsEmbeddedInDesign = useMemo(
    () => documentDesignEmbedsGrids(sourceHtml),
    [sourceHtml],
  );

  const documentCss = useMemo(() => {
    const saved = typeof template.template_css === "string" ? template.template_css.trim() : "";
    if (saved) return saved;
    return defaultEApprovalDocumentDesignCss();
  }, [template]);

  return (
    <>
      <ProcurementPrintPageStyles />
      {documentCss ? <style dangerouslySetInnerHTML={{ __html: documentCss }} /> : null}
      <div className="eapproval-generic-form-print min-h-screen bg-slate-100 print:bg-white">
        <div className="mx-auto max-w-[210mm] bg-white px-6 py-8 shadow-sm print:max-w-none print:px-8 print:py-6 print:shadow-none">
          {documentHtml ? (
            <section
              data-help={fieldsDataHelp}
              className="eapproval-custom-document-design"
              dangerouslySetInnerHTML={{ __html: documentHtml }}
            />
          ) : null}

          {!gridsEmbeddedInDesign
            ? (hydratedData.grids ?? []).map((grid) => (
            <section key={grid.key} className="mt-6 overflow-hidden rounded border border-slate-300">
              <h2 className="border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">
                {grid.label}
              </h2>
              <table className="w-full border-collapse text-xs">
                <thead>
                  <tr className="bg-slate-200 text-left text-[10px] font-medium text-slate-700">
                    {grid.columns.map((column) => (
                      <th key={column} className="border border-slate-300 px-2 py-2">
                        {column}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {grid.rows.length > 0 ? (
                    grid.rows.map((row, rowIndex) => (
                      <tr key={rowIndex} className="odd:bg-white even:bg-slate-50">
                        {row.map((cell, cellIndex) => (
                          <td
                            key={cellIndex}
                            className="border border-slate-200 px-2 py-1.5 align-top text-slate-900"
                          >
                            {cell || "—"}
                          </td>
                        ))}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan={Math.max(grid.columns.length, 1)}
                        className="border border-slate-200 px-2 py-2 text-slate-500"
                      >
                        No rows
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </section>
              ))
            : null}

          {unattachedFiles.length > 0 ? (
            <section className="mt-6 rounded border border-slate-300 p-3">
              <h2 className="text-xs font-medium text-slate-700">Attachments</h2>
              <ul className="mt-2 space-y-1 text-sm text-slate-900">
                {unattachedFiles.map((attachment) => {
                  const meta = attachment.metadata;
                  const caption = [
                    meta?.slot,
                    meta?.caption,
                    meta?.lat != null && meta?.lng != null
                      ? `${Number(meta.lat).toFixed(5)}, ${Number(meta.lng).toFixed(5)}`
                      : null,
                    meta?.captured_at,
                  ]
                    .filter(Boolean)
                    .join(" · ");

                  return (
                    <li key={attachment.id}>
                      <span className="font-medium">{attachment.file_name}</span>
                      {caption ? <span className="block text-xs text-slate-500">{caption}</span> : null}
                    </li>
                  );
                })}
              </ul>
            </section>
          ) : null}

          {showApprovalFooter && shouldShowApprovalHistory(template) ? (
            <div data-help={footerDataHelp}>
              <ApprovalHistoryPrintBlock
                slots={approvalSlots}
                className="eapproval-generic-form-print-footer mt-8"
                variant="screen"
              />
            </div>
          ) : null}

          <div className={showApprovalFooter ? "print:pb-[48mm]" : undefined} aria-hidden />
        </div>
      </div>
    </>
  );
}
