"use client";

import { useMemo } from "react";

import { ApprovalHistoryPrintBlock } from "@/components/e-approval/print/approval-history-print-block";
import {
  ProcurementPrintHeader,
  ProcurementPrintPageStyles,
} from "@/components/e-approval/print/procurement-print-shell";
import {
  buildApprovalHistorySlots,
  resolvePrintTemplate,
  shouldShowApprovalHistory,
} from "@/modules/e-approval/approval-history-print";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type Props = {
  data: EApprovalPrintPayload;
  showApprovalFooter?: boolean;
};

function formatStatus(status: string): string {
  return status
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function attachmentsForField(
  attachments: EApprovalPrintPayload["attachments"],
  fieldKey: string,
): { file_name: string; caption: string | null }[] {
  return (attachments ?? [])
    .filter((attachment) => (attachment.field_name ?? "") === fieldKey)
    .map((attachment) => {
      const meta = attachment.metadata;
      const parts: string[] = [];
      if (meta?.slot) {
        parts.push(meta.slot);
      }
      if (meta?.caption) {
        parts.push(meta.caption);
      }
      if (meta?.lat != null && meta?.lng != null) {
        parts.push(`${Number(meta.lat).toFixed(5)}, ${Number(meta.lng).toFixed(5)}`);
      }
      if (meta?.captured_at) {
        parts.push(meta.captured_at);
      }
      return {
        file_name: attachment.file_name,
        caption: parts.length > 0 ? parts.join(" · ") : null,
      };
    });
}

export function EApprovalGenericFormPrintView({ data, showApprovalFooter = true }: Props) {
  const template = resolvePrintTemplate(data);
  const approvalSlots = useMemo(() => buildApprovalHistorySlots(data, template), [data, template]);
  const gridKeys = useMemo(() => new Set((data.grids ?? []).map((grid) => grid.key)), [data.grids]);
  const scalarFields = useMemo(
    () => data.fields.filter((field) => !gridKeys.has(field.key)),
    [data.fields, gridKeys],
  );
  const unattachedFiles = useMemo(
    () =>
      (data.attachments ?? []).filter(
        (attachment) => !attachment.field_name || !data.fields.some((field) => field.key === attachment.field_name),
      ),
    [data.attachments, data.fields],
  );

  const title = template.header?.title?.trim() || data.form_name?.trim() || "Submission";

  return (
    <>
      <ProcurementPrintPageStyles />
      <div className="eapproval-generic-form-print min-h-screen bg-slate-100 print:bg-white">
        <div className="mx-auto max-w-[210mm] bg-white px-6 py-8 shadow-sm print:max-w-none print:px-8 print:py-6 print:shadow-none">
          <ProcurementPrintHeader data={data} template={template} title={title} />

          {scalarFields.length > 0 ? (
            <section className="mt-6 overflow-hidden rounded border border-slate-300">
              <table className="w-full border-collapse text-sm">
                <tbody>
                  {scalarFields.map((field) => {
                    const files = attachmentsForField(data.attachments, field.key);
                    const value = field.value?.trim() ?? "";

                    return (
                      <tr key={field.key} className="border-b border-slate-200 last:border-b-0">
                        <th
                          scope="row"
                          className="w-[34%] border-r border-slate-200 bg-slate-50 px-3 py-2 text-left align-top text-xs font-medium text-slate-600"
                        >
                          {field.label}
                        </th>
                        <td className="px-3 py-2 align-top text-slate-900">
                          {value ? <p className="whitespace-pre-wrap">{value}</p> : null}
                          {files.length > 0 ? (
                            <ul className={value ? "mt-1 space-y-0.5" : "space-y-0.5"}>
                              {files.map((file) => (
                                <li key={file.file_name} className="text-xs text-slate-700">
                                  <span className="font-medium">{file.file_name}</span>
                                  {file.caption ? (
                                    <span className="block text-[11px] text-slate-500">{file.caption}</span>
                                  ) : null}
                                </li>
                              ))}
                            </ul>
                          ) : null}
                          {!value && files.length === 0 ? <span className="text-slate-400">—</span> : null}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </section>
          ) : null}

          {(data.grids ?? []).map((grid) => (
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
                          <td key={cellIndex} className="border border-slate-200 px-2 py-1.5 align-top text-slate-900">
                            {cell || "—"}
                          </td>
                        ))}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={grid.columns.length} className="px-3 py-4 text-center text-slate-500">
                        No rows
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </section>
          ))}

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

          {data.show_approval_trail && data.approvals.length > 0 ? (
            <section className="mt-6 overflow-hidden rounded border border-slate-300">
              <h2 className="border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">
                Approval trail
              </h2>
              <table className="w-full border-collapse text-xs">
                <thead>
                  <tr className="bg-slate-200 text-left text-[10px] font-medium text-slate-700">
                    <th className="border border-slate-300 px-2 py-2">Step</th>
                    <th className="border border-slate-300 px-2 py-2">Approver</th>
                    <th className="border border-slate-300 px-2 py-2">Status</th>
                    <th className="border border-slate-300 px-2 py-2">Acted at</th>
                    <th className="border border-slate-300 px-2 py-2">Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  {data.approvals.map((row, index) => (
                    <tr key={`${row.step ?? "x"}-${row.approver ?? index}`} className="odd:bg-white even:bg-slate-50">
                      <td className="border border-slate-200 px-2 py-1.5">{row.step ?? "—"}</td>
                      <td className="border border-slate-200 px-2 py-1.5">{row.approver ?? "—"}</td>
                      <td className="border border-slate-200 px-2 py-1.5">{formatStatus(row.status)}</td>
                      <td className="border border-slate-200 px-2 py-1.5">{row.acted_at ?? "—"}</td>
                      <td className="border border-slate-200 px-2 py-1.5 whitespace-pre-wrap">
                        {row.remarks?.trim() || "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>
          ) : null}

          {showApprovalFooter && shouldShowApprovalHistory(template) ? (
            <ApprovalHistoryPrintBlock
              slots={approvalSlots}
              className="eapproval-generic-form-print-footer mt-8"
              variant="screen"
            />
          ) : null}

          <div className={showApprovalFooter ? "print:pb-[48mm]" : undefined} aria-hidden />
        </div>
      </div>
    </>
  );
}
