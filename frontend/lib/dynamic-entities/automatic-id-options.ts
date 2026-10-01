/** Automatic ID field options (Metacoresoft Options tab parity). */

export type DynAutomaticIdOptionsConfig = {
  id_prefix: string;
  id_format: string;
};

export function emptyDynAutomaticIdOptions(): DynAutomaticIdOptionsConfig {
  return { id_prefix: "", id_format: "####" };
}

export function parseDynAutomaticIdOptions(options: unknown): DynAutomaticIdOptionsConfig {
  const base = emptyDynAutomaticIdOptions();
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return base;
  }
  const o = options as Record<string, unknown>;
  const format = String(o.id_format ?? o.format ?? base.id_format).trim();
  return {
    id_prefix: String(o.id_prefix ?? o.prefix ?? ""),
    id_format: format !== "" ? format : base.id_format,
  };
}

export function serializeDynAutomaticIdOptions(
  config: DynAutomaticIdOptionsConfig,
): Record<string, unknown> | null {
  const payload: Record<string, unknown> = {};
  const prefix = config.id_prefix;
  const format = config.id_format.trim() || "####";
  if (prefix !== "") payload.id_prefix = prefix;
  payload.id_format = format;
  return payload;
}
