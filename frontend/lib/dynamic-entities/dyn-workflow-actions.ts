/** Metacoresoft-style Workflow Button actions (GET / WHEN / THEN). */

import { dynSelectChoiceValues } from "@/lib/dynamic-entities/select-choices";

export type DynWorkflowCondition = {
  field: string;
  op: "eq" | "neq";
  value: string;
};

export type DynWorkflowValueMode = "set" | "copy" | "system" | "ask" | "expr";

export type DynWorkflowUpdate = {
  /** "this" or a GET load alias */
  target: string;
  field: string;
  mode: DynWorkflowValueMode;
  value: string;
  /** When mode=copy: source as "this.field" or "alias.field" */
  from?: string;
};

export type DynWorkflowLoad = {
  /** Relationship field on this record */
  source_field: string;
  /** Short name for later steps */
  alias: string;
};

export type DynWorkflowCreateMapping = {
  field: string;
  mode: DynWorkflowValueMode;
  value: string;
  from?: string;
};

export type DynWorkflowCreate = {
  entity_slug: string;
  /** null/empty = just one record; otherwise one per row of loaded list alias */
  one_per: string | null;
  mappings: DynWorkflowCreateMapping[];
};

export type DynWorkflowEmail = {
  subject: string;
  to: string;
  body: string;
  /** Optional slug from Manage Email Templates — fills subject/body/default_to when set */
  template_slug?: string;
};

export type DynWorkflowActionDef = {
  id: string;
  label: string;
  from_status: string;
  to_status: string;
  confirm?: string | null;
  variant?: "default" | "outline" | "secondary" | "destructive";
  role_ids?: string[];
  when: DynWorkflowCondition[];
  loads: DynWorkflowLoad[];
  then_updates: DynWorkflowUpdate[];
  creates: DynWorkflowCreate[];
  emails: DynWorkflowEmail[];
};

const BY_ENTITY: Record<string, DynWorkflowActionDef[]> = {
  bir_form_2307_certificates: [
    {
      id: "post_certificate",
      label: "Post Certificate",
      from_status: "Draft",
      to_status: "Posted",
      confirm: null,
      variant: "default",
      role_ids: [],
      when: [{ field: "status", op: "eq", value: "Draft" }],
      loads: [],
      then_updates: [{ target: "this", field: "status", mode: "set", value: "Posted" }],
      creates: [],
      emails: [],
    },
    {
      id: "cancel_certificate",
      label: "Cancel Certificate",
      from_status: "Posted",
      to_status: "Cancelled",
      confirm: "Are you sure you want to cancel this certificate?",
      variant: "outline",
      role_ids: [],
      when: [{ field: "status", op: "eq", value: "Posted" }],
      loads: [],
      then_updates: [{ target: "this", field: "status", mode: "set", value: "Cancelled" }],
      creates: [],
      emails: [],
    },
  ],
};

function slugify(raw: string): string {
  return raw
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_|_$/g, "");
}

function parseMode(raw: unknown): DynWorkflowValueMode {
  const m = String(raw ?? "set").toLowerCase();
  if (m === "copy" || m === "system" || m === "ask" || m === "expr") return m;
  return "set";
}

function parseConditions(raw: unknown, fallbackFrom?: string): DynWorkflowCondition[] {
  if (Array.isArray(raw) && raw.length > 0) {
    return raw
      .map((row): DynWorkflowCondition | null => {
        if (!row || typeof row !== "object") return null;
        const r = row as Record<string, unknown>;
        const field = String(r.field ?? "").trim() || "status";
        const op = String(r.op ?? "eq") === "neq" ? "neq" : "eq";
        const value = String(r.value ?? "").trim();
        if (!value) return null;
        return { field, op, value };
      })
      .filter((c): c is DynWorkflowCondition => c !== null);
  }
  if (fallbackFrom?.trim()) {
    return [{ field: "status", op: "eq", value: fallbackFrom.trim() }];
  }
  return [];
}

function parseUpdates(raw: unknown, fallbackTo?: string): DynWorkflowUpdate[] {
  if (Array.isArray(raw) && raw.length > 0) {
    return raw
      .map((row): DynWorkflowUpdate | null => {
        if (!row || typeof row !== "object") return null;
        const r = row as Record<string, unknown>;
        const field = String(r.field ?? "").trim() || "status";
        const mode = parseMode(r.mode);
        const value = String(r.value ?? "").trim();
        const from = String(r.from ?? "").trim();
        const target = String(r.target ?? "this").trim() || "this";
        if (mode === "set" && !value) return null;
        if (mode === "copy" && !from) return null;
        return {
          target,
          field,
          mode,
          value: mode === "set" ? value : value,
          from: from || undefined,
        };
      })
      .filter((u): u is DynWorkflowUpdate => u !== null);
  }
  if (fallbackTo?.trim()) {
    return [{ target: "this", field: "status", mode: "set", value: fallbackTo.trim() }];
  }
  return [];
}

function parseLoads(raw: unknown): DynWorkflowLoad[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((row): DynWorkflowLoad | null => {
      if (!row || typeof row !== "object") return null;
      const r = row as Record<string, unknown>;
      const source_field = String(r.source_field ?? r.sourceField ?? "").trim();
      const alias = slugify(String(r.alias ?? ""));
      if (!source_field || !alias) return null;
      return { source_field, alias };
    })
    .filter((l): l is DynWorkflowLoad => l !== null);
}

function parseCreates(raw: unknown): DynWorkflowCreate[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((row): DynWorkflowCreate | null => {
      if (!row || typeof row !== "object") return null;
      const r = row as Record<string, unknown>;
      const entity_slug = String(r.entity_slug ?? r.entitySlug ?? "").trim();
      if (!entity_slug) return null;
      const onePerRaw = r.one_per ?? r.onePer;
      const one_per =
        onePerRaw == null || String(onePerRaw).trim() === ""
          ? null
          : slugify(String(onePerRaw));
      const mappingsRaw = Array.isArray(r.mappings) ? r.mappings : [];
      const mappings: DynWorkflowCreateMapping[] = [];
      for (const m of mappingsRaw) {
        if (!m || typeof m !== "object") continue;
        const mr = m as Record<string, unknown>;
        const field = String(mr.field ?? "").trim();
        const mode = parseMode(mr.mode);
        const value = String(mr.value ?? "").trim();
        const from = String(mr.from ?? "").trim();
        if (!field) continue;
        if (mode === "set" && !value) continue;
        if (mode === "copy" && !from) continue;
        mappings.push({ field, mode, value, from: from || undefined });
      }
      return { entity_slug, one_per, mappings };
    })
    .filter((c): c is DynWorkflowCreate => c !== null);
}

function parseEmails(raw: unknown): DynWorkflowEmail[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((row): DynWorkflowEmail | null => {
      if (!row || typeof row !== "object") return null;
      const r = row as Record<string, unknown>;
      const to = String(r.to ?? "").trim();
      const subject = String(r.subject ?? "").trim();
      const body = String(r.body ?? "").trim();
      const templateSlug = String(r.template_slug ?? "").trim();
      if (!to && !templateSlug) return null;
      return {
        to,
        subject: subject || "Workflow notification",
        body,
        ...(templateSlug ? { template_slug: templateSlug } : {}),
      };
    })
    .filter((e): e is DynWorkflowEmail => e !== null);
}

export function normalizeWorkflowButton(
  row: Record<string, unknown>,
  usedIds: Set<string>,
): DynWorkflowActionDef | null {
  const label = String(row.label ?? "").trim();
  if (!label) return null;

  const legacyFrom = String(row.from_status ?? row.fromStatus ?? "").trim();
  const legacyTo = String(row.to_status ?? row.toStatus ?? "").trim();
  const when = parseConditions(row.when, legacyFrom);
  const then_updates = parseUpdates(row.then_updates ?? row.thenUpdates, legacyTo);
  if (when.length === 0 || then_updates.length === 0) return null;

  const from_status =
    when.find((c) => c.field === "status")?.value ?? when[0]?.value ?? legacyFrom;
  const to_status =
    then_updates.find((u) => u.field === "status" && u.mode === "set")?.value ??
    then_updates.find((u) => u.field === "status")?.value ??
    legacyTo;

  let id = slugify(String(row.id ?? "")) || slugify(label);
  if (!id) return null;
  let unique = id;
  let n = 2;
  while (usedIds.has(unique)) {
    unique = `${id}_${n}`;
    n += 1;
  }
  usedIds.add(unique);

  const variantRaw = String(row.variant ?? "default").toLowerCase();
  const variant = (
    ["default", "outline", "secondary", "destructive"].includes(variantRaw)
      ? variantRaw
      : "default"
  ) as DynWorkflowActionDef["variant"];

  const confirmRaw = row.confirm;
  const confirm =
    typeof confirmRaw === "string" && confirmRaw.trim() !== "" ? confirmRaw.trim() : null;

  const roleRaw = row.role_ids ?? row.roleIds;
  const role_ids = Array.isArray(roleRaw)
    ? roleRaw.map((x) => String(x).trim()).filter(Boolean)
    : [];

  return {
    id: unique,
    label,
    from_status,
    to_status,
    confirm,
    variant,
    role_ids,
    when,
    loads: parseLoads(row.loads),
    then_updates,
    creates: parseCreates(row.creates),
    emails: parseEmails(row.emails),
  };
}

export function parseWorkflowButtons(options: unknown): DynWorkflowActionDef[] {
  let list: unknown[] = [];
  if (Array.isArray(options)) {
    list = options;
  } else if (options && typeof options === "object" && Array.isArray((options as { buttons?: unknown }).buttons)) {
    list = (options as { buttons: unknown[] }).buttons;
  }

  const out: DynWorkflowActionDef[] = [];
  const used = new Set<string>();
  for (const row of list) {
    if (!row || typeof row !== "object") continue;
    const normalized = normalizeWorkflowButton(row as Record<string, unknown>, used);
    if (normalized) out.push(normalized);
  }
  return out;
}

export function workflowButtonsPayload(buttons: DynWorkflowActionDef[]): { buttons: DynWorkflowActionDef[] } {
  return {
    buttons: buttons.map((b) => ({
      id: b.id,
      label: b.label,
      from_status: b.from_status,
      to_status: b.to_status,
      confirm: b.confirm ?? null,
      variant: b.variant ?? "default",
      role_ids: b.role_ids ?? [],
      when: b.when,
      loads: b.loads ?? [],
      then_updates: b.then_updates,
      creates: b.creates ?? [],
      emails: b.emails ?? [],
    })),
  };
}

export function dynWorkflowActionsForEntity(slug: string): DynWorkflowActionDef[] {
  return BY_ENTITY[slug] ?? [];
}

export function dynWorkflowActionsFromFields(
  slug: string,
  fields: Array<{ name: string; options?: unknown }> | undefined,
): DynWorkflowActionDef[] {
  const wf = fields?.find((f) => f.name === "workflows");
  const configured = parseWorkflowButtons(wf?.options);
  if (configured.length > 0) return configured;
  return dynWorkflowActionsForEntity(slug);
}

export function fieldValueFromRecord(
  field: string,
  status: string | null | undefined,
  values: Record<string, unknown> | undefined,
): string {
  if (field === "status") {
    const fromValues = values?.status;
    if (fromValues != null && String(fromValues).trim() !== "") return String(fromValues);
    return (status ?? "").trim() || "Draft";
  }
  const raw = values?.[field];
  return raw == null ? "" : String(raw);
}

export function workflowConditionsMatch(
  action: DynWorkflowActionDef,
  status: string | null | undefined,
  values: Record<string, unknown> | undefined,
): boolean {
  return action.when.every((c) => {
    const current = fieldValueFromRecord(c.field, status, values);
    if (c.op === "neq") return current.toLowerCase() !== c.value.toLowerCase();
    return current.toLowerCase() === c.value.toLowerCase();
  });
}

export function dynWorkflowActionsForStatus(
  slug: string,
  status: string | null | undefined,
  fields?: Array<{ name: string; options?: unknown }>,
  values?: Record<string, unknown>,
): DynWorkflowActionDef[] {
  return dynWorkflowActionsFromFields(slug, fields).filter((a) =>
    workflowConditionsMatch(a, status, values),
  );
}

export function newEmptyWorkflowButton(): DynWorkflowActionDef {
  return {
    id: "",
    label: "",
    from_status: "Draft",
    to_status: "Posted",
    confirm: null,
    variant: "default",
    role_ids: [],
    when: [{ field: "status", op: "eq", value: "Draft" }],
    loads: [],
    then_updates: [{ target: "this", field: "status", mode: "set", value: "Posted" }],
    creates: [],
    emails: [],
  };
}

export function workflowButtonSummary(action: DynWorkflowActionDef): string {
  const c = action.when.length;
  const u = action.then_updates.length;
  const g = action.loads?.length ?? 0;
  const cr = action.creates?.length ?? 0;
  const e = action.emails?.length ?? 0;
  const parts = [
    `${c} condition${c === 1 ? "" : "s"}`,
    `${u} update${u === 1 ? "" : "s"}`,
  ];
  if (g) parts.push(`${g} load${g === 1 ? "" : "s"}`);
  if (cr) parts.push(`${cr} create${cr === 1 ? "" : "s"}`);
  if (e) parts.push(`${e} email${e === 1 ? "" : "s"}`);
  return parts.join(" · ");
}

/** Parse select/dropdown choices from dyn field options. */
export function fieldChoiceOptions(options: unknown): string[] {
  return dynSelectChoiceValues(options);
}
