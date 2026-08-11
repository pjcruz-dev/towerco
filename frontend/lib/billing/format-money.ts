export function formatMoney(
  amount: number,
  currency: string,
  options?: { maximumFractionDigits?: number },
): string {
  const code = currency.trim().toUpperCase() || "USD";
  const maximumFractionDigits = options?.maximumFractionDigits ?? 0;

  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: code,
      maximumFractionDigits,
    }).format(amount);
  } catch {
    return `${code} ${amount.toFixed(maximumFractionDigits)}`;
  }
}
