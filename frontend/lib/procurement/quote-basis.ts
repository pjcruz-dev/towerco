export type ProcurementQuoteBasis =
  | "one_time"
  | "monthly"
  | "yearly"
  | "monthly_yearly";

export function quoteBasisLabel(basis?: string | null): string {
  switch (basis) {
    case "monthly":
      return "Monthly";
    case "yearly":
      return "Yearly";
    case "monthly_yearly":
      return "Monthly + Yearly";
    default:
      return "One-time";
  }
}

export function allowsMonthlyQuote(basis?: string | null): boolean {
  return basis === "monthly" || basis === "monthly_yearly";
}

export function allowsYearlyQuote(basis?: string | null): boolean {
  return basis === "yearly" || basis === "monthly_yearly";
}

export function requiresUnitPrice(basis?: string | null): boolean {
  return !basis || basis === "one_time";
}

export function formatMoney(value: number, currency: string): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
