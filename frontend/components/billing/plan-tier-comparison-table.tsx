"use client";

import { formatMoney } from "@/lib/billing/format-money";
import { cn } from "@/lib/utils";

export type ProcurementCatalogFeatures = {
  enabled?: boolean;
  goods_receipt?: boolean;
  advanced_numbering?: boolean;
  inventory?: boolean;
  ap_invoices?: boolean;
  payment_tracking?: boolean;
  rfq_sourcing?: boolean;
  vendor_contracts?: boolean;
  reporting_exports?: boolean;
};

export type PlanCatalogTier = {
  plan_tier: string;
  label: string;
  sort: number;
  included?: {
    paid_seats?: number;
    rfi_units?: number;
    storage_gb?: number;
  };
  pricing?: {
    monthly_base_usd?: number;
    annual_base_usd?: number;
    rfi_overage_usd?: number;
    paid_seat_overage_usd?: number;
  };
  modules: {
    e_approval?: {
      file_uploads?: boolean;
      max_file_fields?: number | null;
    };
    project_one?: {
      rollout_file_uploads?: boolean;
    };
    ticketing?: {
      enabled?: boolean;
      file_uploads?: boolean;
      max_attachments_per_ticket?: number | null;
    };
    procurement_one?: ProcurementCatalogFeatures;
  };
};

type Props = {
  tiers: PlanCatalogTier[];
  currentTier?: string;
  currency?: string;
  className?: string;
};

function formatFileFields(tier: PlanCatalogTier): string {
  const ea = tier.modules.e_approval;
  if (!ea?.file_uploads) {
    return "Not included";
  }
  if (ea.max_file_fields === null) {
    return "Unlimited";
  }
  return `Up to ${ea.max_file_fields}`;
}

function formatRolloutFiles(tier: PlanCatalogTier): string {
  return tier.modules.project_one?.rollout_file_uploads ? "Included" : "Not included";
}

function formatTicketingModule(tier: PlanCatalogTier): string {
  return tier.modules.ticketing?.enabled ? "Included" : "Not included";
}

function formatTicketAttachments(tier: PlanCatalogTier): string {
  const ticketing = tier.modules.ticketing;
  if (!ticketing?.enabled || !ticketing.file_uploads) {
    return "Not included";
  }
  if (ticketing.max_attachments_per_ticket === null) {
    return "Unlimited";
  }
  return `Up to ${ticketing.max_attachments_per_ticket ?? 0}`;
}

function formatProcurementModule(tier: PlanCatalogTier): string {
  return tier.modules.procurement_one?.enabled ? "Included" : "Not included";
}

function formatProcurementFeature(tier: PlanCatalogTier, key: keyof ProcurementCatalogFeatures): string {
  const procurement = tier.modules.procurement_one;
  if (!procurement?.enabled) {
    return "Not included";
  }

  return procurement[key] ? "Included" : "Not included";
}

function formatOverageRate(amount: number | undefined, currency: string): string {
  if (amount == null || amount <= 0) {
    return "Included";
  }
  return formatMoney(amount, currency);
}

export function PlanTierComparisonTable({ tiers, currentTier, currency = "USD", className }: Props) {
  const sorted = [...tiers].sort((a, b) => a.sort - b.sort);

  return (
    <div className={cn("overflow-x-auto rounded-xl border border-border", className)}>
      <table className="w-full min-w-[520px] border-collapse text-sm">
        <thead>
          <tr className="border-b border-border bg-muted/40 text-left">
            <th className="px-4 py-3 font-medium text-muted-foreground">Feature</th>
            {sorted.map((tier) => (
              <th key={tier.plan_tier} className="px-4 py-3 font-medium text-foreground">
                {tier.label}
                {currentTier === tier.plan_tier ? (
                  <span className="ml-1.5 text-xs font-normal text-primary">(current)</span>
                ) : null}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Included paid seats</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {tier.included?.paid_seats ?? "—"}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Included RFI units</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {tier.included?.rfi_units ?? "—"}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Indicative monthly ({currency})</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {tier.pricing?.monthly_base_usd != null
                  ? formatMoney(tier.pricing.monthly_base_usd, currency)
                  : "—"}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">+1 paid seat / month ({currency})</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatOverageRate(tier.pricing?.paid_seat_overage_usd, currency)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">+1 RFI unit / month ({currency})</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatOverageRate(tier.pricing?.rfi_overage_usd, currency)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">E-Approval form file fields</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatFileFields(tier)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">E-Approval submission attachments</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {tier.modules.e_approval?.file_uploads ? "Allowed" : "Blocked"}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">PROJECT-ONE rollout evidence uploads</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatRolloutFiles(tier)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Ticketing module</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatTicketingModule(tier)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Ticketing ticket attachments</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatTicketAttachments(tier)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement-One module</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementModule(tier)}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement goods receipt</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "goods_receipt")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement inventory</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "inventory")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement AP invoices</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "ap_invoices")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement payment tracking</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "payment_tracking")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement RFQ &amp; sourcing</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "rfq_sourcing")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement vendor contracts</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "vendor_contracts")}
              </td>
            ))}
          </tr>
          <tr>
            <td className="px-4 py-3 text-muted-foreground">Procurement reports &amp; exports</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatProcurementFeature(tier, "reporting_exports")}
              </td>
            ))}
          </tr>
        </tbody>
      </table>
    </div>
  );
}
