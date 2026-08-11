"use client";

import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";

import { fetchProcurementRfqBidVersions } from "@/lib/api/modules/procurement-one-api";
import { formatMoney } from "@/lib/procurement/quote-basis";
import type { ProcurementRfqBidVersion } from "@/modules/procurement-one/types";

type Props = {
  rfqId: string;
  bidId: string;
  vendorName: string | null;
  currencyCode: string;
};

export function ProcurementRfqBidVersionsSection({ rfqId, bidId, vendorName, currencyCode }: Props) {
  const query = useQuery({
    queryKey: ["procurement-one", "rfqs", rfqId, "bids", bidId, "versions"],
    queryFn: () => fetchProcurementRfqBidVersions(rfqId, bidId),
    enabled: Boolean(rfqId && bidId),
  });

  const versions = query.data ?? [];

  if (query.isLoading) {
    return <p className="text-sm text-muted-foreground">Loading quotation history…</p>;
  }

  if (versions.length <= 1) {
    return null;
  }

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h3 className="text-base font-medium">Quotation history — {vendorName ?? "Vendor"}</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        {versions.length} versions recorded. The comparison matrix uses the latest submission.
      </p>
      <div className="mt-4 space-y-3">
        {[...versions].reverse().map((version) => (
          <VersionCard key={version.id} version={version} currencyCode={currencyCode} rfqId={rfqId} bidId={bidId} />
        ))}
      </div>
    </section>
  );
}

function VersionCard({
  version,
  currencyCode,
  rfqId,
  bidId,
}: {
  version: ProcurementRfqBidVersion;
  currencyCode: string;
  rfqId: string;
  bidId: string;
}) {
  const recordedAt = version.recorded_at ? new Date(version.recorded_at).toLocaleString() : "—";
  const viaLabel = version.submitted_via === "portal" ? "Vendor portal" : "Internal capture";
  const pricing = useMemo(() => {
    const showMonthly = version.total_amount_monthly != null && version.total_amount_monthly > 0;
    const showYearly = version.total_amount_yearly != null && version.total_amount_yearly > 0;
    const showNormalized =
      version.normalized_annual_amount != null &&
      version.normalized_annual_amount > 0 &&
      version.normalized_annual_amount !== version.total_amount;

    return { showMonthly, showYearly, showNormalized };
  }, [version]);

  return (
    <div className="rounded-lg border border-border p-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-medium">Version {version.version_no}</p>
          <p className="text-xs text-muted-foreground">
            {recordedAt} · {viaLabel}
            {version.portal_contact_name ? ` · ${version.portal_contact_name}` : ""}
          </p>
        </div>
        <div className="text-right text-sm tabular-nums">
          <p className="font-medium">{formatMoney(version.total_amount, currencyCode)}</p>
          {pricing.showMonthly ? (
            <p className="text-xs text-muted-foreground">
              Monthly: {formatMoney(version.total_amount_monthly!, currencyCode)}
            </p>
          ) : null}
          {pricing.showYearly ? (
            <p className="text-xs text-muted-foreground">
              Yearly: {formatMoney(version.total_amount_yearly!, currencyCode)}
            </p>
          ) : null}
          {pricing.showNormalized ? (
            <p className="text-xs text-muted-foreground">
              Normalized annual: {formatMoney(version.normalized_annual_amount!, currencyCode)}
            </p>
          ) : null}
        </div>
      </div>

      {version.lines.length > 0 ? (
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-xs">
            <thead className="text-left text-muted-foreground">
              <tr>
                <th className="pb-1 pr-3 font-medium">Line</th>
                <th className="pb-1 pr-3 font-medium">Qty</th>
                {version.lines.some((line) => line.quote_basis && line.quote_basis !== "one_time") ? (
                  <>
                    <th className="pb-1 pr-3 font-medium">Monthly</th>
                    <th className="pb-1 pr-3 font-medium">Yearly</th>
                    <th className="pb-1 pr-3 font-medium">Annual</th>
                  </>
                ) : (
                  <th className="pb-1 pr-3 font-medium">Unit price</th>
                )}
                <th className="pb-1 font-medium">Amount</th>
              </tr>
            </thead>
            <tbody>
              {version.lines.map((line) => (
                <tr key={line.rfq_line_id} className="border-t border-border/60">
                  <td className="py-1.5 pr-3">{line.description ?? "—"}</td>
                  <td className="py-1.5 pr-3 tabular-nums">{line.quantity}</td>
                  {line.quote_basis && line.quote_basis !== "one_time" ? (
                    <>
                      <td className="py-1.5 pr-3 tabular-nums">
                        {line.amount_monthly != null ? formatMoney(line.amount_monthly, currencyCode) : "—"}
                      </td>
                      <td className="py-1.5 pr-3 tabular-nums">
                        {line.amount_yearly != null ? formatMoney(line.amount_yearly, currencyCode) : "—"}
                      </td>
                      <td className="py-1.5 pr-3 tabular-nums">
                        {line.normalized_annual_amount != null
                          ? formatMoney(line.normalized_annual_amount, currencyCode)
                          : "—"}
                      </td>
                    </>
                  ) : (
                    <td className="py-1.5 pr-3 tabular-nums">{formatMoney(line.unit_price, currencyCode)}</td>
                  )}
                  <td className="py-1.5 tabular-nums">{formatMoney(line.amount, currencyCode)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {version.attachments.length > 0 ? (
        <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
          {version.attachments.map((file) => (
            <li key={file.id}>
              <a
                href={`/api/v1/procurement-one/rfqs/${rfqId}/bids/${bidId}/attachments/${file.id}/download`}
                className="text-primary hover:underline"
              >
                {file.file_name}
              </a>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
