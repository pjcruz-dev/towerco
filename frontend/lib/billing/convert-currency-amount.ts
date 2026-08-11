export function convertCurrencyAmount(
  amount: number,
  fromCurrency: string,
  toCurrency: string,
  rates: Record<string, number>,
): number {
  const from = fromCurrency.trim().toUpperCase() || "USD";
  const to = toCurrency.trim().toUpperCase() || "USD";

  if (!Number.isFinite(amount) || amount <= 0 || from === to) {
    return amount;
  }

  const fromRate = rates[from] ?? 1;
  const toRate = rates[to] ?? 1;
  const usd = from === "USD" ? amount : amount / fromRate;
  const converted = to === "USD" ? usd : usd * toRate;

  if (to === "JPY" || to === "IDR" || to === "VND" || to === "PHP" || to === "THB" || to === "INR") {
    return Math.round(converted);
  }

  return Math.round(converted * 100) / 100;
}
