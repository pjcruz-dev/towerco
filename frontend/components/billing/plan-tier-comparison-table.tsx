"use client";

import { formatMoney } from "@/lib/billing/format-money";
import { cn } from "@/lib/utils";

export type PlanCatalogTier = {
  plan_tier: string;
  label: string;
  sort: number;
  included?: {
    paid_seats?: number;
    tower_licenses?: number;
    /** @deprecated Prefer tower_licenses */
    rfi_units?: number;
    storage_gb?: number;
  };
  pricing?: {
    monthly_base_usd?: number;
    annual_base_usd?: number;
    tower_overage_usd?: number;
    /** @deprecated Prefer tower_overage_usd */
    rfi_overage_usd?: number;
    paid_seat_overage_usd?: number;
  };
  modules: {
    e_approval?: {
      file_uploads?: boolean;
      max_file_fields?: number | null;
    };
    ticketing?: {
      enabled?: boolean;
      file_uploads?: boolean;
      max_attachments_per_ticket?: number | null;
    };
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

function formatOverageRate(amount: number | undefined, currency: string): string {
  if (amount == null || amount <= 0) {
    return "Included";
  }
  return formatMoney(amount, currency);
}

function includedTowerLicenses(tier: PlanCatalogTier): number | undefined {
  return tier.included?.tower_licenses ?? tier.included?.rfi_units;
}

function towerOverage(tier: PlanCatalogTier): number | undefined {
  return tier.pricing?.tower_overage_usd ?? tier.pricing?.rfi_overage_usd;
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
            <td className="px-4 py-3 text-muted-foreground">Included tower licenses</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {includedTowerLicenses(tier) ?? "—"}
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
            <td className="px-4 py-3 text-muted-foreground">+1 tower license / month ({currency})</td>
            {sorted.map((tier) => (
              <td key={tier.plan_tier} className="px-4 py-3 text-foreground">
                {formatOverageRate(towerOverage(tier), currency)}
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
        </tbody>
      </table>
    </div>
  );
}
