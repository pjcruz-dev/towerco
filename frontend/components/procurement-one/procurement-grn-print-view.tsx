"use client";

import type { ProcurementGrnPrintPayload } from "@/modules/procurement-one/types";

export function ProcurementGrnPrintPageStyles() {
  return (
    <style
      dangerouslySetInnerHTML={{
        __html: `@media print {
            @page { size: A4; margin: 10mm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .procurement-grn-print { background: #fff !important; }
          }`,
      }}
    />
  );
}

type Props = {
  data: ProcurementGrnPrintPayload;
};

export function ProcurementGrnPrintView({ data }: Props) {
  return (
    <>
      <ProcurementGrnPrintPageStyles />
      <div className="procurement-grn-print min-h-screen bg-slate-100 print:bg-white">
        <div className="mx-auto max-w-[210mm] bg-white px-6 py-8 shadow-sm print:max-w-none print:px-8 print:py-6 print:shadow-none">
          <header className="border-b border-slate-300 pb-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-xs text-slate-600">{data.brand}</p>
                <p className="mt-1 text-sm text-slate-700">Procurement-One</p>
              </div>
              <div className="text-right">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Goods Receipt Note</h1>
                <p className="mt-1 text-sm text-slate-600">{data.status_label}</p>
                <p className="text-sm font-medium text-slate-900">{data.document_no ?? "—"}</p>
                <p className="text-xs text-slate-500">
                  {data.posted_at ? new Date(data.posted_at).toLocaleString() : "—"}
                </p>
              </div>
            </div>
          </header>

          <section className="mt-4 grid gap-px border border-slate-300 bg-slate-300 text-sm md:grid-cols-2">
            {[
              { label: "Purchase order", value: data.po_document_no ?? data.po_id },
              { label: "Supplier", value: data.supplier ?? "—" },
              { label: "Received by", value: data.received_by?.name ?? "—" },
              {
                label: "Received at",
                value: data.received_at ? new Date(data.received_at).toLocaleString() : "—",
              },
            ].map((row) => (
              <div key={row.label} className="bg-white p-3">
                <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{row.label}</p>
                <p className="mt-1 font-medium text-slate-900">{row.value}</p>
              </div>
            ))}
          </section>

          {data.site_id || (data.gps_latitude != null && data.gps_longitude != null) ? (
            <section className="mt-3 grid gap-px border border-slate-300 bg-slate-300 text-sm md:grid-cols-2">
              {data.site_id ? (
                <div className="bg-white p-3">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Site</p>
                  <p className="mt-1 font-mono text-xs text-slate-900">{data.site_id}</p>
                </div>
              ) : null}
              {data.gps_latitude != null && data.gps_longitude != null ? (
                <div className="bg-white p-3">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">GPS</p>
                  <p className="mt-1 font-mono text-xs text-slate-900">
                    {data.gps_latitude.toFixed(6)}, {data.gps_longitude.toFixed(6)}
                    {data.gps_accuracy_meters != null ? ` (±${data.gps_accuracy_meters}m)` : ""}
                  </p>
                </div>
              ) : null}
            </section>
          ) : null}

          {data.notes ? (
            <section className="mt-3 border border-slate-300 p-3 text-sm">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</p>
              <p className="mt-1 text-slate-800">{data.notes}</p>
            </section>
          ) : null}

          <section className="mt-4">
            <table className="w-full border-collapse text-sm">
              <thead>
                <tr className="border border-slate-300 bg-slate-50 text-left text-slate-600">
                  <th className="border border-slate-300 px-2 py-1.5 font-medium">Description</th>
                  <th className="border border-slate-300 px-2 py-1.5 font-medium">UOM</th>
                  <th className="border border-slate-300 px-2 py-1.5 font-medium text-right">Ordered</th>
                  <th className="border border-slate-300 px-2 py-1.5 font-medium text-right">Received</th>
                </tr>
              </thead>
              <tbody>
                {data.lines.map((line, index) => (
                  <tr key={`${line.description}-${index}`}>
                    <td className="border border-slate-300 px-2 py-1.5">{line.description}</td>
                    <td className="border border-slate-300 px-2 py-1.5">{line.uom ?? "EA"}</td>
                    <td className="border border-slate-300 px-2 py-1.5 text-right tabular-nums">
                      {line.quantity_ordered}
                    </td>
                    <td className="border border-slate-300 px-2 py-1.5 text-right tabular-nums">
                      {line.quantity_received}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          {data.mismatches.length > 0 ? (
            <section className="mt-4 border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
              <p className="font-medium">Receipt variances</p>
              <ul className="mt-2 list-disc space-y-1 pl-5">
                {data.mismatches.map((row, index) => (
                  <li key={`${row.type}-${index}`}>{row.message}</li>
                ))}
              </ul>
            </section>
          ) : null}

          {data.attachments.length > 0 ? (
            <section className="mt-4 text-sm">
              <p className="font-medium text-slate-800">Delivery photos on file</p>
              <ul className="mt-2 list-disc pl-5 text-slate-700">
                {data.attachments.map((attachment, index) => (
                  <li key={`${attachment.file_name}-${index}`}>{attachment.file_name}</li>
                ))}
              </ul>
            </section>
          ) : null}

          <footer className="mt-8 border-t border-slate-200 pt-3 text-xs text-slate-500">
            Generated from TowerOS Procurement-One · {new Date(data.printed_at).toLocaleString()}
          </footer>
        </div>
      </div>
    </>
  );
}
