/** Client-side heuristics for Report Builder “Build it” NL input. */

import type { DynEntityDetail, DynReportBuilderDef } from "@/lib/api/modules/dynamic-entities-api";

export function inferReportBuilderFromPrompt(
  prompt: string,
  entities: Array<{ slug: string; name: string }>,
  fieldsBySlug: Record<string, DynEntityDetail | undefined>,
): Partial<DynReportBuilderDef> {
  const text = prompt.trim().toLowerCase();
  if (!text) return {};

  const out: Partial<DynReportBuilderDef> = {
    title: prompt.trim().slice(0, 120),
  };

  const entity =
    entities.find((e) => text.includes(e.slug.replace(/_/g, " ")) || text.includes(e.slug)) ??
    entities.find((e) => text.includes(e.name.toLowerCase())) ??
    null;
  if (entity) out.entity_slug = entity.slug;

  if (/\bmatrix\b|cross[- ]?tab|pivot/.test(text)) out.format = "matrix";
  else if (/\bdetail\b|every record|line item|list of/.test(text)) out.format = "detail";
  else out.format = "summary";

  if (/\bsum\b|total amount|total po|amount by/.test(text)) out.metric = "sum";
  else out.metric = "count";

  if (/\bpie\b/.test(text)) out.chart = "pie";
  else if (/\bline\b/.test(text)) out.chart = "line";
  else if (/\btable\b|no chart/.test(text)) out.chart = "none";
  else out.chart = "bar";

  if (/\bthis year\b|by year/.test(text)) out.date_grouping = "year";
  else if (/\bthis month\b|by month|monthly/.test(text)) out.date_grouping = "month";
  else if (/\bweekly\b|by week/.test(text)) out.date_grouping = "week";
  else out.date_grouping = "exact";

  const detail = entity ? fieldsBySlug[entity.slug] : undefined;
  const fields = detail?.fields ?? [];
  const fieldNames = fields.map((f) => f.name);

  const amountField =
    fieldNames.find((n) => /amount|total|price|cost|value/.test(n)) ??
    fields.find((f) => f.calculate_totals)?.name;
  if (out.metric === "sum" && amountField) out.metric_field = amountField;

  const groupHint =
    fieldNames.find((n) => text.includes(n.replace(/_/g, " ")) || text.includes(n)) ??
    fieldNames.find((n) => /category|status|type|warehouse|tower|site|vendor|department/.test(n));
  if (groupHint) out.group_by = groupHint;

  const byMatch = text.match(/\bby\s+([a-z0-9_ ]{2,40})/);
  if (byMatch) {
    const token = byMatch[1].trim().replace(/\s+/g, "_");
    const hit =
      fieldNames.find((n) => n === token) ??
      fieldNames.find((n) => n.includes(token) || token.includes(n));
    if (hit) out.group_by = hit;
  }

  return out;
}
