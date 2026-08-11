"use client";

import { quoteBasisLabel } from "@/lib/procurement/quote-basis";

type RfqLine = {
  id: string;
  line_order: number;
  description: string;
  uom: string | null;
  quantity: number;
  target_unit_price?: number | null;
  quote_basis?: string | null;
  quote_basis_label?: string | null;
};

function formatMoney(value: number, currency: string): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function lineAmount(line: RfqLine): number | null {
  if (line.target_unit_price == null) {
    return null;
  }

  return line.quantity * line.target_unit_price;
}

type Props = {
  lines: RfqLine[];
  currencyCode: string;
  estimatedTotal?: number | null;
  /** Hide target prices (e.g. vendor-facing copy). */
  hideTargetPrices?: boolean;
  linesSource?: "purchase_requisition" | "rfq";
};

export function ProcurementRfqLinesSection({
  lines,
  currencyCode,
  estimatedTotal,
  hideTargetPrices = false,
  linesSource,
}: Props) {
  const currency = currencyCode || "PHP";
  const computedTotal = lines.reduce((sum, line) => sum + (lineAmount(line) ?? 0), 0);
  const showTotals = !hideTargetPrices && lines.some((line) => line.target_unit_price != null);
  const footerTotal = estimatedTotal ?? (showTotals ? computedTotal : null);

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-medium">Line items</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {lines.length} {lines.length === 1 ? "item" : "items"} to quote
            {hideTargetPrices
              ? " — enter your unit prices below."
              : linesSource === "purchase_requisition"
                ? " — synced from the linked purchase requisition."
                : " — target estimates from the linked PR."}
          </p>
        </div>
        {footerTotal != null && footerTotal > 0 ? (
          <div className="text-right">
            <p className="text-xs text-muted-foreground">Estimated total</p>
            <p className="text-sm font-medium tabular-nums text-foreground">{formatMoney(footerTotal, currency)}</p>
          </div>
        ) : null}
      </div>

      {lines.length === 0 ? (
        <p className="mt-4 text-sm text-muted-foreground">No line items on this RFQ.</p>
      ) : (
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          <table className="w-full min-w-[32rem] text-sm">
            <thead className="bg-muted/30 text-left text-xs text-muted-foreground">
              <tr>
                <th className="px-3 py-2.5 font-medium w-10">#</th>
                <th className="px-3 py-2.5 font-medium">Description</th>
                <th className="px-3 py-2.5 font-medium w-20">UOM</th>
                <th className="px-3 py-2.5 font-medium w-28">Quote basis</th>
                <th className="px-3 py-2.5 font-medium text-right w-24">Qty</th>
                {!hideTargetPrices ? (
                  <>
                    <th className="px-3 py-2.5 font-medium text-right w-32">Target unit</th>
                    <th className="px-3 py-2.5 font-medium text-right w-32">Line total</th>
                  </>
                ) : null}
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => {
                const amount = lineAmount(line);

                return (
                  <tr key={line.id} className="border-t border-border/60">
                    <td className="px-3 py-2.5 tabular-nums text-muted-foreground">{line.line_order}</td>
                    <td className="px-3 py-2.5 font-medium text-foreground">{line.description}</td>
                    <td className="px-3 py-2.5 uppercase text-muted-foreground">{line.uom ?? "EA"}</td>
                    <td className="px-3 py-2.5 text-muted-foreground">
                      {line.quote_basis_label ?? quoteBasisLabel(line.quote_basis)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {line.quantity.toLocaleString(undefined, { maximumFractionDigits: 4 })}
                    </td>
                    {!hideTargetPrices ? (
                      <>
                        <td className="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                          {line.target_unit_price != null ? formatMoney(line.target_unit_price, currency) : "—"}
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums font-medium">
                          {amount != null ? formatMoney(amount, currency) : "—"}
                        </td>
                      </>
                    ) : null}
                  </tr>
                );
              })}
            </tbody>
            {showTotals && footerTotal != null ? (
              <tfoot>
                <tr className="border-t border-border bg-muted/20">
                  <td colSpan={6} className="px-3 py-2.5 text-right text-xs font-medium text-muted-foreground">
                    Estimated total
                  </td>
                  <td className="px-3 py-2.5 text-right text-sm font-semibold tabular-nums">
                    {formatMoney(footerTotal, currency)}
                  </td>
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>
      )}
    </section>
  );
}
