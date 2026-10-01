/** Number / decimal field formatting options (Metacoresoft Options tab parity). */

export type DynNumberOptionsConfig = {
  currency_symbol: string;
  decimal_places: string; // "" = auto
  default_value: string;
  thousand_separators: boolean;
};

export function emptyDynNumberOptions(): DynNumberOptionsConfig {
  return {
    currency_symbol: "",
    decimal_places: "",
    default_value: "",
    thousand_separators: false,
  };
}

export function parseDynNumberOptions(options: unknown): DynNumberOptionsConfig {
  const base = emptyDynNumberOptions();
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return base;
  }
  const o = options as Record<string, unknown>;
  const decimalRaw = o.decimal_places ?? o.decimals ?? o.precision;
  return {
    currency_symbol: String(o.currency_symbol ?? o.currency ?? "").trim(),
    decimal_places:
      decimalRaw === null || decimalRaw === undefined || String(decimalRaw).trim() === ""
        ? ""
        : String(decimalRaw).trim(),
    default_value: String(o.default_value ?? o.default ?? "").trim(),
    thousand_separators: Boolean(
      o.thousand_separators ?? o.thousands_separator ?? o.use_thousands_separator,
    ),
  };
}

/** Persist number options; returns null when everything is empty/default. */
export function serializeDynNumberOptions(
  config: DynNumberOptionsConfig,
): Record<string, unknown> | null {
  const currency = config.currency_symbol.trim();
  const decimals = config.decimal_places.trim();
  const defaultValue = config.default_value.trim();
  const payload: Record<string, unknown> = {};
  if (currency) payload.currency_symbol = currency;
  if (decimals !== "") {
    const n = Number(decimals);
    payload.decimal_places = Number.isFinite(n) ? Math.max(0, Math.min(12, Math.trunc(n))) : decimals;
  }
  if (defaultValue !== "") payload.default_value = defaultValue;
  if (config.thousand_separators) payload.thousand_separators = true;
  return Object.keys(payload).length > 0 ? payload : null;
}

export function formatDynNumberValue(
  value: unknown,
  options: unknown,
  fallbackCurrency = false,
): string | null {
  if (value === null || value === undefined || value === "") return null;
  const n = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(n)) return null;

  const cfg = parseDynNumberOptions(options);
  const decimals =
    cfg.decimal_places === ""
      ? fallbackCurrency
        ? 2
        : undefined
      : Math.max(0, Math.min(12, Number(cfg.decimal_places) || 0));

  const formatted = n.toLocaleString("en-PH", {
    useGrouping: cfg.thousand_separators || fallbackCurrency,
    minimumFractionDigits: decimals ?? (fallbackCurrency ? 2 : 0),
    maximumFractionDigits: decimals ?? (fallbackCurrency ? 2 : 4),
  });

  const symbol = cfg.currency_symbol || (fallbackCurrency ? "₱" : "");
  if (!symbol) return formatted;
  return `${symbol} ${formatted}`.replace(/\s+/g, " ").trim();
}
