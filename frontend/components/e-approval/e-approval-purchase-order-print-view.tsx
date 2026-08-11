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
import { PO_GRID_FIELD_NAME } from "@/modules/e-approval/purchase-order-template";
import { resolvePrintTemplateEntry } from "@/modules/e-approval/print-template-registry";
import {
  formatPrintMoney,
  printFieldValueMap,
  resolvePrintCurrency,
} from "@/modules/e-approval/print-utils";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  data: EApprovalPrintPayload;
  showApprovalFooter?: boolean;
};

export function isPurchaseOrderPrintPayload(data: EApprovalPrintPayload): boolean {
  const entry = resolvePrintTemplateEntry(data);
  return entry?.kind === "purchase_order";
}

export function EApprovalPurchaseOrderPrintView({ data, showApprovalFooter = true }: Props) {
  const template = resolvePrintTemplate(data);
  const values = useMemo(() => {
    const map = printFieldValueMap(data);
    return {
      ...map,
      supplier: map.supplier || map.vendor || "",
      ship_to: map.ship_to || map.delivery_location || "",
      delivery_date: map.delivery_date || map.required_delivery_date || "",
      grand_total: map.grand_total || map.total_vat_inclusive || map.total_amount || "",
      total_vat_inclusive: map.total_vat_inclusive || map.grand_total || "",
      currency_code: map.currency_code || map.currency || "PHP",
    };
  }, [data]);
  const currency = resolvePrintCurrency(values, data);
  const lineGrid =
    data.grids?.find((grid) => grid.key === PO_GRID_FIELD_NAME) ?? data.grids?.[0] ?? null;
  const approvalSlots = useMemo(() => buildApprovalHistorySlots(data, template), [data, template]);

  const approvedNames = data.approvals
    .filter((row) => row.status.toLowerCase() === "approved")
    .map((row) => row.approver)
    .filter(Boolean);

  const preparedBy = values.prepared_by?.trim() || data.requestor || "—";

  return (
    <>
      <ProcurementPrintPageStyles />
      <div className="eapproval-procurement-print min-h-screen bg-slate-100 print:bg-white">
        <div className="mx-auto max-w-[210mm] bg-white px-6 py-8 shadow-sm print:max-w-none print:px-8 print:py-6 print:shadow-none">
          <ProcurementPrintHeader data={data} template={template} title="Purchase Order" />

          <section className="mt-4 grid gap-3 border border-slate-300 md:grid-cols-2">
            <div className="border-b border-slate-200 p-3 md:border-b-0 md:border-r">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Supplier</p>
              <p className="mt-1 whitespace-pre-wrap text-sm text-slate-900">{values.supplier || "—"}</p>
            </div>
            <div className="p-3">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Ship to</p>
              <p className="mt-1 whitespace-pre-wrap text-sm text-slate-900">{values.ship_to || "—"}</p>
            </div>
          </section>

          <section className="mt-3 grid grid-cols-2 gap-px border border-slate-300 bg-slate-300 text-sm md:grid-cols-4">
            {[
              { label: "Delivery date", value: values.delivery_date },
              { label: "Terms", value: values.payment_terms },
              { label: "Currency", value: values.currency_code },
              { label: "Exchange rate", value: values.exchange_rate },
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
                  {(lineGrid?.columns ?? ["Item", "Description", "UOM", "Qty", "Unit price", "Discount", "Amount"]).map(
                    (column) => (
                      <th key={column} className="border border-slate-300 px-2 py-2">
                        {column}
                      </th>
                    ),
                  )}
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
                      colSpan={lineGrid?.columns.length ?? 7}
                      className="px-3 py-4 text-center text-slate-500"
                    >
                      No line items
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </section>

          <section className="mt-4 grid gap-4 md:grid-cols-2">
            <div className="border border-slate-300">
              <div className="bg-slate-200 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-700">
                Tax summary
              </div>
              <table className="w-full text-xs">
                <tbody>
                  {[
                    { label: "VATable amount", value: values.vatable_amount },
                    { label: "VAT-exempt amount", value: values.vat_exempt_amount },
                    { label: "Zero-rated amount", value: values.zero_rated_amount },
                    { label: "VAT amount", value: values.vat_amount },
                  ].map((row) => (
                    <tr key={row.label} className="border-t border-slate-200">
                      <td className="px-3 py-1.5 text-slate-600">{row.label}</td>
                      <td className="px-3 py-1.5 text-right font-medium text-slate-900">
                        {formatPrintMoney(row.value, currency)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex flex-col justify-end">
              <div className="ml-auto w-full max-w-xs border border-slate-400 bg-slate-100">
                {[
                  { label: "Total (VAT inclusive)", value: values.total_vat_inclusive, strong: false },
                  { label: "Less: Discount", value: values.less_discount, strong: false },
                  { label: "Total amount", value: values.grand_total, strong: true },
                ].map((row) => (
                  <div
                    key={row.label}
                    className={cn(
                      "flex items-center justify-between border-b border-slate-300 px-3 py-2 text-sm last:border-b-0",
                      row.strong && "bg-slate-200 font-semibold",
                    )}
                  >
                    <span className="text-slate-700">{row.label}</span>
                    <span className="text-slate-900">{formatPrintMoney(row.value, currency)}</span>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="mt-6 grid gap-4 border-t border-slate-300 pt-4 text-sm md:grid-cols-2">
            <div>
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Prepared by</p>
              <p className="mt-1 font-medium text-slate-900">{preparedBy}</p>
            </div>
            <div>
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Approved by</p>
              <p className="mt-1 text-slate-900">{approvedNames.length > 0 ? approvedNames.join(" · ") : "—"}</p>
            </div>
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
