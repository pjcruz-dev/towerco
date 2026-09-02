"use client";

import { useState } from "react";
import { ChevronDown, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  fieldChoiceOptions,
  newEmptyWorkflowButton,
  workflowButtonSummary,
  type DynWorkflowActionDef,
  type DynWorkflowCondition,
  type DynWorkflowCreate,
  type DynWorkflowEmail,
  type DynWorkflowLoad,
  type DynWorkflowUpdate,
  type DynWorkflowValueMode,
} from "@/lib/dynamic-entities/dyn-workflow-actions";
import { cn } from "@/lib/utils";

type FieldOption = {
  name: string;
  label: string;
  type?: string;
  options?: unknown;
  target_entity_slug?: string | null;
};

type Props = {
  buttons: DynWorkflowActionDef[];
  onChange: (next: DynWorkflowActionDef[]) => void;
  fieldOptions?: FieldOption[];
  roleOptions?: Array<{ id: string; name: string }>;
  entityOptions?: Array<{ slug: string; name: string }>;
};

const selectClass =
  "h-9 w-full rounded-md border border-input bg-background px-3 text-sm";

const VALUE_MODES: Array<{ value: DynWorkflowValueMode; label: string }> = [
  { value: "set", label: "Set a value" },
  { value: "copy", label: "Copy from another field" },
  { value: "system", label: "System value" },
  { value: "ask", label: "Ask when it runs" },
  { value: "expr", label: "Custom expression" },
];

export function DynWorkflowButtonsEditor({
  buttons,
  onChange,
  fieldOptions = [],
  roleOptions = [],
  entityOptions = [],
}: Props) {
  const [openIndex, setOpenIndex] = useState<number | null>(buttons.length > 0 ? 0 : null);

  const fields: FieldOption[] = [
    { name: "status", label: "Status", type: "select" },
    ...fieldOptions.filter(
      (f) =>
        !["workflows", "actions", "print", "id"].includes(f.name) && f.name !== "status",
    ),
  ];

  const relationshipFields = fields.filter(
    (f) => f.type === "relationship" || Boolean(f.target_entity_slug),
  );

  function update(index: number, patch: Partial<DynWorkflowActionDef>) {
    onChange(
      buttons.map((b, i) => {
        if (i !== index) return b;
        const next = { ...b, ...patch };
        const statusWhen = next.when.find((c) => c.field === "status");
        const statusThen = next.then_updates.find((u) => u.field === "status" && u.mode === "set");
        if (statusWhen) next.from_status = statusWhen.value;
        if (statusThen) next.to_status = statusThen.value;
        return next;
      }),
    );
  }

  function remove(index: number) {
    onChange(buttons.filter((_, i) => i !== index));
    setOpenIndex((cur) => {
      if (cur === null) return null;
      if (cur === index) return null;
      if (cur > index) return cur - 1;
      return cur;
    });
  }

  function sync(index: number, next: DynWorkflowActionDef) {
    update(index, next);
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">
          Buttons appear when WHEN matches. Clicking runs GET loads, THEN updates / creates / emails.
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => {
            onChange([...buttons, newEmptyWorkflowButton()]);
            setOpenIndex(buttons.length);
          }}
        >
          <Plus className="size-3.5" />
          Add Button
        </Button>
      </div>

      {buttons.length === 0 ? (
        <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center">
          <p className="text-sm text-muted-foreground">No workflow buttons yet.</p>
        </div>
      ) : null}

      {buttons.map((button, index) => {
        const open = openIndex === index;
        const loadAliases = [
          { id: "this", label: "This record" },
          ...(button.loads ?? []).map((l) => ({ id: l.alias, label: l.alias })),
        ];
        const copySources = buildCopySources(fields, button.loads ?? []);

        return (
          <div
            key={`${button.id || "new"}-${index}`}
            className="overflow-hidden rounded-xl border border-border bg-card"
          >
            <button
              type="button"
              className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-muted/40"
              onClick={() => setOpenIndex(open ? null : index)}
            >
              <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky-600 text-[11px] font-semibold text-white">
                {index + 1}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-foreground">
                  {button.label.trim() || `Button ${index + 1}`}
                </span>
                <span className="block text-[11px] text-muted-foreground">
                  {workflowButtonSummary(button)}
                </span>
              </span>
              <ChevronDown
                className={cn(
                  "size-4 shrink-0 text-muted-foreground transition-transform",
                  open && "rotate-180",
                )}
              />
            </button>

            {open ? (
              <div className="space-y-4 border-t border-border px-3 py-4">
                <div className="flex justify-end">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() => remove(index)}
                  >
                    <Trash2 className="size-3.5" />
                    Delete
                  </Button>
                </div>

                <label className="block space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Button text</span>
                  <Input
                    value={button.label}
                    onChange={(e) => update(index, { label: e.target.value })}
                    placeholder="Post Certificate"
                  />
                </label>

                <div className="grid gap-3 sm:grid-cols-2">
                  <label className="block space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Button colour</span>
                    <select
                      className={selectClass}
                      value={button.variant ?? "default"}
                      onChange={(e) =>
                        update(index, {
                          variant: e.target.value as DynWorkflowActionDef["variant"],
                        })
                      }
                    >
                      <option value="default">Primary</option>
                      <option value="outline">Outline</option>
                      <option value="secondary">Secondary</option>
                      <option value="destructive">Destructive</option>
                    </select>
                  </label>
                  <label className="block space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">
                      Who can press it
                    </span>
                    <select
                      className={selectClass}
                      multiple
                      value={button.role_ids ?? []}
                      onChange={(e) => {
                        const selected = Array.from(e.target.selectedOptions).map((o) => o.value);
                        update(index, { role_ids: selected });
                      }}
                      size={Math.min(4, Math.max(2, roleOptions.length || 2))}
                    >
                      {roleOptions.length === 0 ? (
                        <option value="" disabled>
                          Leave empty for everyone
                        </option>
                      ) : (
                        roleOptions.map((r) => (
                          <option key={r.id} value={r.id}>
                            {r.name}
                          </option>
                        ))
                      )}
                    </select>
                    <span className="text-[11px] text-muted-foreground">
                      Leave empty for everyone. Hold Ctrl/Cmd to multi-select.
                    </span>
                  </label>
                </div>

                <label className="block space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">
                    Confirmation prompt (optional)
                  </span>
                  <Input
                    value={button.confirm ?? ""}
                    onChange={(e) =>
                      update(index, {
                        confirm: e.target.value.trim() === "" ? null : e.target.value,
                      })
                    }
                    placeholder="Are you sure you want to approve this?"
                  />
                </label>

                <WhenSection
                  fields={fields}
                  when={button.when}
                  onChange={(when) =>
                    sync(index, {
                      ...button,
                      when: when.length ? when : [{ field: "status", op: "eq", value: "Draft" }],
                    })
                  }
                />

                <GetSection
                  relationshipFields={relationshipFields}
                  loads={button.loads ?? []}
                  onChange={(loads) => sync(index, { ...button, loads })}
                />

                <ThenUpdatesSection
                  fields={fields}
                  loadAliases={loadAliases}
                  copySources={copySources}
                  updates={button.then_updates}
                  onChange={(then_updates) =>
                    sync(index, {
                      ...button,
                      then_updates: then_updates.length
                        ? then_updates
                        : [{ target: "this", field: "status", mode: "set", value: "Posted" }],
                    })
                  }
                />

                <CreateSection
                  entityOptions={entityOptions}
                  loadAliases={(button.loads ?? []).map((l) => l.alias)}
                  creates={button.creates ?? []}
                  copySources={copySources}
                  onChange={(creates) => sync(index, { ...button, creates })}
                />

                <EmailSection
                  emails={button.emails ?? []}
                  onChange={(emails) => sync(index, { ...button, emails })}
                />
              </div>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}

function buildCopySources(fields: FieldOption[], loads: DynWorkflowLoad[]) {
  const out: Array<{ id: string; label: string }> = [];
  for (const f of fields) {
    out.push({ id: `this.${f.name}`, label: `This record · ${f.label}` });
  }
  for (const load of loads) {
    out.push({ id: `${load.alias}.status`, label: `${load.alias} · Status` });
    out.push({ id: `${load.alias}.title`, label: `${load.alias} · Title` });
  }
  return out;
}

function WhenSection({
  fields,
  when,
  onChange,
}: {
  fields: FieldOption[];
  when: DynWorkflowCondition[];
  onChange: (when: DynWorkflowCondition[]) => void;
}) {
  return (
    <Block
      badge="WHEN"
      badgeClass="bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100"
      title="Conditions"
      count={when.length}
      hint="Only run when these are true."
    >
      {when.map((cond, ci) => {
        const fieldMeta = fields.find((f) => f.name === cond.field);
        const choices = fieldChoiceOptions(fieldMeta?.options);
        return (
          <div key={ci} className="grid gap-2 sm:grid-cols-[1fr_auto_1fr_auto] sm:items-start">
            <select
              className={selectClass}
              value={cond.field}
              onChange={(e) =>
                onChange(when.map((c, i) => (i === ci ? { ...c, field: e.target.value } : c)))
              }
            >
              {fields.map((f) => (
                <option key={f.name} value={f.name}>
                  {f.label}
                </option>
              ))}
            </select>
            <select
              className={selectClass}
              value={cond.op}
              onChange={(e) =>
                onChange(
                  when.map((c, i) =>
                    i === ci ? { ...c, op: e.target.value as "eq" | "neq" } : c,
                  ),
                )
              }
            >
              <option value="eq">is</option>
              <option value="neq">is not</option>
            </select>
            <ValueInput
              choices={choices}
              value={cond.value}
              onChange={(value) =>
                onChange(when.map((c, i) => (i === ci ? { ...c, value } : c)))
              }
              placeholder="Draft"
            />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-8 text-destructive"
              disabled={when.length <= 1}
              onClick={() => onChange(when.filter((_, i) => i !== ci))}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
        );
      })}
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() => onChange([...when, { field: "status", op: "eq", value: "" }])}
      >
        <Plus className="size-3.5" />
        Add condition
      </Button>
    </Block>
  );
}

function GetSection({
  relationshipFields,
  loads,
  onChange,
}: {
  relationshipFields: FieldOption[];
  loads: DynWorkflowLoad[];
  onChange: (loads: DynWorkflowLoad[]) => void;
}) {
  return (
    <Block
      badge="GET"
      badgeClass="bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-100"
      title="Related records to load"
      count={loads.length}
      hint="Pull in a linked record under a short name, so updates below can point at it."
    >
      {loads.map((load, li) => (
        <div key={li} className="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
          <select
            className={selectClass}
            value={load.source_field}
            onChange={(e) =>
              onChange(
                loads.map((l, i) => (i === li ? { ...l, source_field: e.target.value } : l)),
              )
            }
          >
            <option value="">-- What should be loaded? --</option>
            <optgroup label="Linked records">
              {relationshipFields.map((f) => (
                <option key={f.name} value={f.name}>
                  {f.label}
                </option>
              ))}
            </optgroup>
          </select>
          <Input
            value={load.alias}
            onChange={(e) =>
              onChange(loads.map((l, i) => (i === li ? { ...l, alias: e.target.value } : l)))
            }
            placeholder="Call it... e.g. linked_supplier"
          />
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-8 text-destructive"
            onClick={() => onChange(loads.filter((_, i) => i !== li))}
          >
            <Trash2 className="size-3.5" />
          </Button>
        </div>
      ))}
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={relationshipFields.length === 0}
        onClick={() =>
          onChange([
            ...loads,
            {
              source_field: relationshipFields[0]?.name ?? "",
              alias: "linked_record",
            },
          ])
        }
      >
        <Plus className="size-3.5" />
        Add related load
      </Button>
      {relationshipFields.length === 0 ? (
        <p className="text-[11px] text-muted-foreground">
          No relationship fields on this entity yet (e.g. Supplier Link).
        </p>
      ) : null}
    </Block>
  );
}

function ThenUpdatesSection({
  fields,
  loadAliases,
  copySources,
  updates,
  onChange,
}: {
  fields: FieldOption[];
  loadAliases: Array<{ id: string; label: string }>;
  copySources: Array<{ id: string; label: string }>;
  updates: DynWorkflowUpdate[];
  onChange: (updates: DynWorkflowUpdate[]) => void;
}) {
  return (
    <Block
      badge="THEN"
      badgeClass="bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
      title="Fields to update"
      count={updates.length}
      hint="Set field values on this record, or on anything loaded above."
    >
      {updates.map((upd, ui) => {
        const fieldMeta = fields.find((f) => f.name === upd.field);
        const choices = fieldChoiceOptions(fieldMeta?.options);
        const modeEnabled = upd.mode === "set" || upd.mode === "copy";
        return (
          <div key={ui} className="space-y-2 rounded-lg border border-border/70 bg-background p-2.5">
            <div className="grid gap-2 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-center">
              <select
                className={selectClass}
                value={upd.target}
                onChange={(e) =>
                  onChange(updates.map((u, i) => (i === ui ? { ...u, target: e.target.value } : u)))
                }
              >
                {loadAliases.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.label}
                  </option>
                ))}
              </select>
              <select
                className={selectClass}
                value={upd.field}
                onChange={(e) =>
                  onChange(updates.map((u, i) => (i === ui ? { ...u, field: e.target.value } : u)))
                }
              >
                <option value="">-- Select Field --</option>
                {fields.map((f) => (
                  <option key={f.name} value={f.name}>
                    {f.label}
                  </option>
                ))}
              </select>
              <select
                className={selectClass}
                value={upd.mode}
                onChange={(e) =>
                  onChange(
                    updates.map((u, i) =>
                      i === ui ? { ...u, mode: e.target.value as DynWorkflowValueMode } : u,
                    ),
                  )
                }
              >
                {VALUE_MODES.map((m) => (
                  <option key={m.value} value={m.value} disabled={m.value !== "set" && m.value !== "copy"}>
                    {m.label}
                    {m.value !== "set" && m.value !== "copy" ? " (soon)" : ""}
                  </option>
                ))}
              </select>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 text-destructive"
                disabled={updates.length <= 1}
                onClick={() => onChange(updates.filter((_, i) => i !== ui))}
              >
                <Trash2 className="size-3.5" />
              </Button>
            </div>
            {upd.mode === "set" ? (
              <ValueInput
                choices={choices}
                value={upd.value}
                onChange={(value) =>
                  onChange(updates.map((u, i) => (i === ui ? { ...u, value } : u)))
                }
                placeholder="Posted"
              />
            ) : null}
            {upd.mode === "copy" ? (
              <select
                className={selectClass}
                value={upd.from ?? ""}
                onChange={(e) =>
                  onChange(updates.map((u, i) => (i === ui ? { ...u, from: e.target.value } : u)))
                }
              >
                <option value="">-- Copy from --</option>
                {copySources.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.label}
                  </option>
                ))}
              </select>
            ) : null}
            {!modeEnabled ? (
              <p className="text-[11px] text-muted-foreground">
                This value mode is shown for Metacoresoft parity and is not executed yet.
              </p>
            ) : null}
            {upd.mode === "set" && upd.value ? (
              <p className="text-[11px] text-muted-foreground">stores &quot;{upd.value}&quot;</p>
            ) : null}
          </div>
        );
      })}
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() =>
          onChange([
            ...updates,
            { target: "this", field: "status", mode: "set", value: "" },
          ])
        }
      >
        <Plus className="size-3.5" />
        Add field update
      </Button>
    </Block>
  );
}

function CreateSection({
  entityOptions,
  loadAliases,
  creates,
  copySources,
  onChange,
}: {
  entityOptions: Array<{ slug: string; name: string }>;
  loadAliases: string[];
  creates: DynWorkflowCreate[];
  copySources: Array<{ id: string; label: string }>;
  onChange: (creates: DynWorkflowCreate[]) => void;
}) {
  return (
    <Block
      badge="THEN"
      badgeClass="bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
      title="Records to create"
      count={creates.length}
      hint="Create new records in another entity, optionally one per row of a loaded list."
    >
      {creates.map((create, ci) => (
        <div key={ci} className="space-y-2 rounded-lg border border-border/70 bg-background p-2.5">
          <div className="flex justify-end">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-8 text-destructive"
              onClick={() => onChange(creates.filter((_, i) => i !== ci))}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            <label className="space-y-1">
              <span className="text-[11px] font-medium text-muted-foreground">Create a record in</span>
              <select
                className={selectClass}
                value={create.entity_slug}
                onChange={(e) =>
                  onChange(
                    creates.map((c, i) =>
                      i === ci ? { ...c, entity_slug: e.target.value } : c,
                    ),
                  )
                }
              >
                <option value="">-- Select entity --</option>
                {entityOptions.map((e) => (
                  <option key={e.slug} value={e.slug}>
                    {e.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="space-y-1">
              <span className="text-[11px] font-medium text-muted-foreground">One per row of</span>
              <select
                className={selectClass}
                value={create.one_per ?? ""}
                onChange={(e) =>
                  onChange(
                    creates.map((c, i) =>
                      i === ci
                        ? { ...c, one_per: e.target.value === "" ? null : e.target.value }
                        : c,
                    ),
                  )
                }
              >
                <option value="">-- Just one record --</option>
                {loadAliases.map((a) => (
                  <option key={a} value={a}>
                    {a}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <p className="text-[11px] text-muted-foreground">
            Field mappings — what each field of the new record is set to.
          </p>
          {create.mappings.map((m, mi) => (
            <div key={mi} className="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
              <Input
                value={m.field}
                onChange={(e) =>
                  onChange(
                    creates.map((c, i) =>
                      i === ci
                        ? {
                            ...c,
                            mappings: c.mappings.map((x, j) =>
                              j === mi ? { ...x, field: e.target.value } : x,
                            ),
                          }
                        : c,
                    ),
                  )
                }
                placeholder="Field name"
              />
              <select
                className={selectClass}
                value={m.mode}
                onChange={(e) =>
                  onChange(
                    creates.map((c, i) =>
                      i === ci
                        ? {
                            ...c,
                            mappings: c.mappings.map((x, j) =>
                              j === mi
                                ? { ...x, mode: e.target.value as DynWorkflowValueMode }
                                : x,
                            ),
                          }
                        : c,
                    ),
                  )
                }
              >
                <option value="set">Set a value</option>
                <option value="copy">Copy from</option>
              </select>
              {m.mode === "copy" ? (
                <select
                  className={selectClass}
                  value={m.from ?? ""}
                  onChange={(e) =>
                    onChange(
                      creates.map((c, i) =>
                        i === ci
                          ? {
                              ...c,
                              mappings: c.mappings.map((x, j) =>
                                j === mi ? { ...x, from: e.target.value } : x,
                              ),
                            }
                          : c,
                      ),
                    )
                  }
                >
                  <option value="">-- Source --</option>
                  {copySources.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.label}
                    </option>
                  ))}
                </select>
              ) : (
                <Input
                  value={m.value}
                  onChange={(e) =>
                    onChange(
                      creates.map((c, i) =>
                        i === ci
                          ? {
                              ...c,
                              mappings: c.mappings.map((x, j) =>
                                j === mi ? { ...x, value: e.target.value } : x,
                              ),
                            }
                          : c,
                      ),
                    )
                  }
                  placeholder="Value"
                />
              )}
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 text-destructive"
                onClick={() =>
                  onChange(
                    creates.map((c, i) =>
                      i === ci
                        ? { ...c, mappings: c.mappings.filter((_, j) => j !== mi) }
                        : c,
                    ),
                  )
                }
              >
                <Trash2 className="size-3.5" />
              </Button>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() =>
              onChange(
                creates.map((c, i) =>
                  i === ci
                    ? {
                        ...c,
                        mappings: [...c.mappings, { field: "", mode: "set", value: "" }],
                      }
                    : c,
                ),
              )
            }
          >
            <Plus className="size-3.5" />
            Add mapping
          </Button>
        </div>
      ))}
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() =>
          onChange([
            ...creates,
            {
              entity_slug: entityOptions[0]?.slug ?? "general_ledger",
              one_per: null,
              mappings: [{ field: "status", mode: "set", value: "Posted" }],
            },
          ])
        }
      >
        <Plus className="size-3.5" />
        Add record to create
      </Button>
    </Block>
  );
}

function EmailSection({
  emails,
  onChange,
}: {
  emails: DynWorkflowEmail[];
  onChange: (emails: DynWorkflowEmail[]) => void;
}) {
  return (
    <Block
      badge="THEN"
      badgeClass="bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
      title="Emails to send"
      count={emails.length}
      hint="Inline subject/body, or set Template slug from Manage Email Templates."
    >
      {emails.map((email, ei) => (
        <div key={ei} className="space-y-2 rounded-lg border border-border/70 bg-background p-2.5">
          <Input
            value={email.template_slug ?? ""}
            onChange={(e) =>
              onChange(
                emails.map((x, i) =>
                  i === ei ? { ...x, template_slug: e.target.value.trim() || undefined } : x,
                ),
              )
            }
            placeholder="Template slug (optional)"
          />
          <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
            <Input
              value={email.subject}
              onChange={(e) =>
                onChange(emails.map((x, i) => (i === ei ? { ...x, subject: e.target.value } : x)))
              }
              placeholder="Subject (ignored if template slug set)"
            />
            <Input
              value={email.to}
              onChange={(e) =>
                onChange(emails.map((x, i) => (i === ei ? { ...x, to: e.target.value } : x)))
              }
              placeholder="To: e.g. {self.email}"
            />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-8 text-destructive"
              onClick={() => onChange(emails.filter((_, i) => i !== ei))}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
          <textarea
            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={email.body}
            onChange={(e) =>
              onChange(emails.map((x, i) => (i === ei ? { ...x, body: e.target.value } : x)))
            }
            placeholder="Email body (ignored if template slug set)"
          />
        </div>
      ))}
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() =>
          onChange([
            ...emails,
            { subject: "Workflow notification", to: "{self.email}", body: "", template_slug: "" },
          ])
        }
      >
        <Plus className="size-3.5" />
        Add email
      </Button>
    </Block>
  );
}

function ValueInput({
  choices,
  value,
  onChange,
  placeholder,
}: {
  choices: string[];
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}) {
  if (choices.length > 0) {
    return (
      <select className={selectClass} value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">-- leave blank --</option>
        {choices.map((c) => (
          <option key={c} value={c}>
            {c}
          </option>
        ))}
      </select>
    );
  }
  return <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} />;
}

function Block({
  badge,
  badgeClass,
  title,
  count,
  hint,
  children,
}: {
  badge: string;
  badgeClass: string;
  title: string;
  count: number;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="flex items-center gap-2 text-xs font-semibold text-foreground">
            <span className={cn("rounded px-1.5 py-0.5 text-[10px] font-bold uppercase", badgeClass)}>
              {badge}
            </span>
            {title}
          </p>
          {hint ? <p className="mt-0.5 text-[11px] text-muted-foreground">{hint}</p> : null}
        </div>
        <span className="flex size-5 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground">
          {count}
        </span>
      </div>
      <div className="space-y-2">{children}</div>
    </div>
  );
}
