/** Text / textarea field options (Metacoresoft Options tab parity). */

export type DynTextOptionsConfig = {
  default_value: string;
  prevent_duplicates: boolean;
};

export function emptyDynTextOptions(): DynTextOptionsConfig {
  return { default_value: "", prevent_duplicates: false };
}

export function parseDynTextOptions(options: unknown): DynTextOptionsConfig {
  const base = emptyDynTextOptions();
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return base;
  }
  const o = options as Record<string, unknown>;
  return {
    default_value: String(o.default_value ?? o.default ?? "").trim(),
    prevent_duplicates: Boolean(
      o.prevent_duplicates ?? o.unique ?? o.is_unique ?? o.refuse_duplicates,
    ),
  };
}

export function serializeDynTextOptions(
  config: DynTextOptionsConfig,
): Record<string, unknown> | null {
  const payload: Record<string, unknown> = {};
  const defaultValue = config.default_value.trim();
  if (defaultValue) payload.default_value = defaultValue;
  if (config.prevent_duplicates) payload.prevent_duplicates = true;
  return Object.keys(payload).length > 0 ? payload : null;
}
