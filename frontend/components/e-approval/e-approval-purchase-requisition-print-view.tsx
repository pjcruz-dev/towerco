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
import { PR_GRID_FIELD_NAME } from "@/modules/e-approval/purchase-requisition-template";
import {
  formatPrintMoney,
  printFieldValueMap,
  resolvePrintCurrency,
} from "@/modules/e-approval/print-utils";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type Props = {
  data: EApprovalPrintPayload;
  showApprovalFooter?: boolean;
};

export function EApprovalPurchaseRequisitionPrintView({ data, showApprovalFooter = true }: Props) {
  const template = resolvePrintTemplate(data);
  const values = useMemo(() => printFieldValueMap(data), [data]);
  const currency = resolvePrintCurrency(values, data);
  const lineGrid =
    data.grids?.find((grid) => grid.key === PR_GRID_FIELD_NAME) ?? data.grids?.[0] ?? null;
  const approvalSlots = useMemo(() => buildApprovalHistorySlots(data, template), [data, template]);

  const requestedBy = values.requested_by?.trim() || data.requestor || "—";

  return (
    <>
      <ProcurementPrintPageStyles />
      <div className="eapproval-procurement-print min-h-screen bg-slate-100 print:bg-white">
        <div className="mx-auto max-w-[210mm] bg-white px-6 py-8 shadow-sm print:max-w-none print:px-8 print:py-6 print:shadow-none">
          <ProcurementPrintHeader data={data} template={template} title="Purchase Requisition" />

          <section className="mt-4 grid gap-3 border border-slate-300 md:grid-cols-2">
            <div className="border-b border-slate-200 p-3 md:border-b-0 md:border-r">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Title / summary</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{values.requisition_title || "—"}</p>
            </div>
            <div className="p-3">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Requested by</p>
              <p className="mt-1 text-sm font-medium text-slate-900">{requestedBy}</p>
            </div>
          </section>

          <section className="mt-3 grid grid-cols-2 gap-px border border-slate-300 bg-slate-300 text-sm md:grid-cols-4">
            {[
              { label: "Department", value: values.department },
              { label: "Urgency", value: values.urgency },
              { label: "Currency", value: values.currency },
              { label: "Needed by", value: values.needed_by },
            ].map((item) => (
              <div key={item.label} className="bg-slate-100 px-3 py-2">
                <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{item.label}</p>
                <p className="mt-0.5 font-medium text-slate-900">{item.value || "—"}</p>
              </div>
            ))}
          </section>

          <section className="mt-4 overflow-hidden rounded border border-slate-300">
            <table className="w-full border-collapse text-xs">
              <thead>
                <tr className="bg-slate-200 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">
                  {(lineGrid?.columns ?? ["Description", "Qty", "Unit price", "Amount"]).map((column) => (
                    <th key={column} className="border border-slate-300 px-2 py-2">
                      {column}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(lineGrid?.rows ?? []).length > 0 ? (
                  lineGrid!.rows.map((row, rowIndex) => (
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
                    <td
                      colSpan={lineGrid?.columns.length ?? 4}
                      className="px-3 py-4 text-center text-slate-500"
                    >
                      No line items
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </section>

          <section className="mt-4 flex justify-end">
            <div className="w-full max-w-xs border border-slate-400 bg-slate-100 px-3 py-2">
              <div className="flex items-center justify-between text-sm">
                <span className="font-semibold text-slate-700">Estimated total</span>
                <span className="font-semibold text-slate-900">
                  {formatPrintMoney(values.estimated_total, currency)}
                </span>
              </div>
            </div>
          </section>

          <section className="mt-4 border border-slate-300 p-3">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Business justification</p>
            <p className="mt-2 whitespace-pre-wrap text-sm text-slate-900">{values.justification || "—"}</p>
          </section>

          {showApprovalFooter && shouldShowApprovalHistory(template) ? (
            <ApprovalHistoryPrintBlock
              slots={approvalSlots}
              className="eapproval-procurement-print-footer mt-8"
              variant="screen"
            />
          ) : null}

          <div className="print:pb-[48mm]" aria-hidden />
        </div>
      </div>
    </>
  );
}
