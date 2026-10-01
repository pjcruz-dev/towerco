"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft, Plus, Save, Trash2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  createDynEntityHook,
  fetchDynEntities,
  fetchDynEntityHook,
  updateDynEntityHook,
  type DynEntityHookAction,
  type DynEntityHookDefinition,
  type DynEntityHookEvent,
  type DynEntityHookWhenRule,
  type DynEntitySummary,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";

const ALL_EVENTS: Array<{ id: DynEntityHookEvent; label: string; hint: string }> = [
  { id: "before_create", label: "Before create", hint: "Just before a new record is inserted." },
  { id: "after_create", label: "After create", hint: "After a new record is saved." },
  { id: "before_update", label: "Before update", hint: "Before an existing record is written." },
  { id: "after_update", label: "After update", hint: "After an existing record is saved." },
  { id: "before_delete", label: "Before delete", hint: "Before a record is soft-deleted." },
  { id: "after_delete", label: "After delete", hint: "After a record is soft-deleted." },
  { id: "before_action", label: "Before action", hint: "Before a workflow button applies its changes." },
];

const WHEN_OPS: Array<{ id: DynEntityHookWhenRule["op"]; label: string }> = [
  { id: "filled", label: "is filled" },
  { id: "empty", label: "is empty" },
  { id: "eq", label: "equals" },
  { id: "neq", label: "not equals" },
  { id: "changed", label: "changed (update)" },
];

function emptyDefinition(): DynEntityHookDefinition {
  return {
    when: [],
    actions: [{ type: "mirror_field", from: "", to: "" }],
  };
}

function DynEntityHookEditorInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const id = searchParams.get("id") ?? "";
  const isNew = id === "";

  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [entitySlug, setEntitySlug] = useState("");
  const [events, setEvents] = useState<DynEntityHookEvent[]>(["before_create", "before_update"]);
  const [isActive, setIsActive] = useState(true);
  const [definition, setDefinition] = useState<DynEntityHookDefinition>(emptyDefinition);
  const [tab, setTab] = useState<"build" | "code">("build");
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const ents = await fetchDynEntities({ active_only: true });
      setEntities(ents);
      if (!isNew) {
        const row = await fetchDynEntityHook(id);
        setName(row.name);
        setDescription(row.description ?? "");
        setEntitySlug(row.entity_slug);
        setEvents(row.events.length > 0 ? row.events : ["before_create"]);
        setIsActive(row.is_active);
        setDefinition({
          when: row.definition_json?.when ?? [],
          actions:
            row.definition_json?.actions?.length > 0
              ? row.definition_json.actions
              : [{ type: "mirror_field", from: "", to: "" }],
        });
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [id, isNew]);

  useEffect(() => {
    void load();
  }, [load]);

  const codePreview = useMemo(
    () => JSON.stringify(definition, null, 2),
    [definition],
  );

  function toggleEvent(event: DynEntityHookEvent) {
    setEvents((prev) =>
      prev.includes(event) ? prev.filter((e) => e !== event) : [...prev, event],
    );
  }

  function updateWhen(index: number, patch: Partial<DynEntityHookWhenRule>) {
    setDefinition((prev) => ({
      ...prev,
      when: prev.when.map((rule, i) => (i === index ? { ...rule, ...patch } : rule)),
    }));
  }

  function updateAction(index: number, next: DynEntityHookAction) {
    setDefinition((prev) => ({
      ...prev,
      actions: prev.actions.map((action, i) => (i === index ? next : action)),
    }));
  }

  async function onSave() {
    setSaving(true);
    setError(null);
    try {
      const payload = {
        name: name.trim(),
        description: description.trim() || undefined,
        entity_slug: entitySlug,
        events,
        definition_json: definition,
        is_active: isActive,
      };
      if (isNew) {
        const created = await createDynEntityHook(payload);
        router.replace(`/dynamic-entities/hooks/edit?id=${created.id}`);
      } else {
        await updateDynEntityHook(id, {
          ...payload,
          description: description.trim() || null,
        });
      }
      router.push("/dynamic-entities/hooks");
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.entityHooksManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title={isNew ? "Create Hook" : "Edit Hook"}
          description="Build declarative rules that run on entity lifecycle events. The Code tab shows the stored DSL JSON."
          actions={
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                render={<Link href="/dynamic-entities/hooks" />}
              >
                <ArrowLeft className="size-4" />
                Back
              </Button>
              <Button type="button" size="sm" disabled={saving || loading} onClick={() => void onSave()}>
                <Save className="size-4" />
                {saving ? "Saving…" : "Save Hook"}
              </Button>
            </>
          }
        />

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : (
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <section className="space-y-5 rounded-xl border bg-card p-5 shadow-sm">
              <div className="space-y-2">
                <Label htmlFor="hook-name">Name</Label>
                <Input
                  id="hook-name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="AT-029 — company_id mirrors subsidiary_id"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="hook-entity">Entity</Label>
                <Select
                  id="hook-entity"
                  value={entitySlug}
                  onChange={(e) => setEntitySlug(e.target.value)}
                >
                  <option value="">Select entity…</option>
                  {entities.map((ent) => (
                    <option key={ent.id} value={ent.slug}>
                      {ent.name} ({ent.slug})
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-2">
                <Label>When should it run?</Label>
                <div className="space-y-2">
                  {ALL_EVENTS.map((event) => {
                    const checked = events.includes(event.id);
                    return (
                      <label
                        key={event.id}
                        className={cn(
                          "flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5",
                          checked ? "border-primary/40 bg-muted/40" : "border-border",
                        )}
                      >
                        <Checkbox
                          checked={checked}
                          onCheckedChange={() => toggleEvent(event.id)}
                          className="mt-0.5"
                        />
                        <span>
                          <span className="block text-sm font-medium">{event.label}</span>
                          <span className="block text-xs text-muted-foreground">{event.hint}</span>
                        </span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="hook-desc">Description</Label>
                <Textarea
                  id="hook-desc"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={3}
                  placeholder="Optional notes for operators"
                />
              </div>

              <div className="flex items-center justify-between rounded-lg border px-3 py-2.5">
                <div>
                  <div className="text-sm font-medium">Active — run this hook</div>
                  <div className="text-xs text-muted-foreground">Inactive hooks are kept but skipped.</div>
                </div>
                <Switch checked={isActive} onCheckedChange={setIsActive} />
              </div>

              <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-200">
                Make rules idempotent. One save may run the same before-hook path more than once in nested updates.
              </div>
            </section>

            <section className="rounded-xl border bg-card shadow-sm">
              <div className="flex border-b">
                <button
                  type="button"
                  className={cn(
                    "flex-1 px-4 py-3 text-sm font-medium",
                    tab === "build" ? "border-b-2 border-primary text-foreground" : "text-muted-foreground",
                  )}
                  onClick={() => setTab("build")}
                >
                  Build
                </button>
                <button
                  type="button"
                  className={cn(
                    "flex-1 px-4 py-3 text-sm font-medium",
                    tab === "code" ? "border-b-2 border-primary text-foreground" : "text-muted-foreground",
                  )}
                  onClick={() => setTab("code")}
                >
                  Code
                </button>
              </div>

              {tab === "build" ? (
                <div className="space-y-6 p-5">
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <h3 className="text-sm font-medium">When (optional conditions)</h3>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                          setDefinition((prev) => ({
                            ...prev,
                            when: [...prev.when, { field: "", op: "filled" }],
                          }))
                        }
                      >
                        <Plus className="size-4" />
                        Add condition
                      </Button>
                    </div>
                    {definition.when.length === 0 ? (
                      <p className="text-xs text-muted-foreground">No conditions — actions always run.</p>
                    ) : (
                      definition.when.map((rule, index) => (
                        <div key={index} className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                          <Input
                            value={rule.field}
                            onChange={(e) => updateWhen(index, { field: e.target.value })}
                            placeholder="field"
                          />
                          <Select
                            value={rule.op}
                            onChange={(e) =>
                              updateWhen(index, {
                                op: e.target.value as DynEntityHookWhenRule["op"],
                              })
                            }
                          >
                            {WHEN_OPS.map((op) => (
                              <option key={op.id} value={op.id}>
                                {op.label}
                              </option>
                            ))}
                          </Select>
                          {rule.op === "eq" || rule.op === "neq" ? (
                            <Input
                              value={rule.value ?? ""}
                              onChange={(e) => updateWhen(index, { value: e.target.value })}
                              placeholder="value"
                            />
                          ) : (
                            <div className="text-xs text-muted-foreground self-center">—</div>
                          )}
                          <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            onClick={() =>
                              setDefinition((prev) => ({
                                ...prev,
                                when: prev.when.filter((_, i) => i !== index),
                              }))
                            }
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      ))
                    )}
                  </div>

                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <h3 className="text-sm font-medium">Actions</h3>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                          setDefinition((prev) => ({
                            ...prev,
                            actions: [...prev.actions, { type: "mirror_field", from: "", to: "" }],
                          }))
                        }
                      >
                        <Plus className="size-4" />
                        Add a rule
                      </Button>
                    </div>
                    {definition.actions.map((action, index) => (
                      <div key={index} className="space-y-2 rounded-lg border p-3">
                        <div className="flex items-center gap-2">
                          <Select
                            value={action.type}
                            onChange={(e) => {
                              const type = e.target.value as DynEntityHookAction["type"];
                              if (type === "mirror_field") {
                                updateAction(index, { type, from: "", to: "" });
                              } else if (type === "set_field") {
                                updateAction(index, { type, field: "", value: "" });
                              } else {
                                updateAction(index, { type: "clear_field", field: "" });
                              }
                            }}
                          >
                            <option value="mirror_field">Mirror field</option>
                            <option value="set_field">Set field</option>
                            <option value="clear_field">Clear field</option>
                          </Select>
                          <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            onClick={() =>
                              setDefinition((prev) => ({
                                ...prev,
                                actions: prev.actions.filter((_, i) => i !== index),
                              }))
                            }
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                        {action.type === "mirror_field" ? (
                          <div className="grid gap-2 sm:grid-cols-2">
                            <Input
                              value={action.from}
                              onChange={(e) =>
                                updateAction(index, { ...action, from: e.target.value })
                              }
                              placeholder="from (e.g. subsidiary_id)"
                            />
                            <Input
                              value={action.to}
                              onChange={(e) =>
                                updateAction(index, { ...action, to: e.target.value })
                              }
                              placeholder="to (e.g. company_id)"
                            />
                          </div>
                        ) : null}
                        {action.type === "set_field" ? (
                          <div className="grid gap-2 sm:grid-cols-2">
                            <Input
                              value={action.field}
                              onChange={(e) =>
                                updateAction(index, { ...action, field: e.target.value })
                              }
                              placeholder="field"
                            />
                            <Input
                              value={String(action.value ?? "")}
                              onChange={(e) =>
                                updateAction(index, { ...action, value: e.target.value })
                              }
                              placeholder="value or $now / $actor_id / $record_id"
                            />
                          </div>
                        ) : null}
                        {action.type === "clear_field" ? (
                          <Input
                            value={action.field}
                            onChange={(e) =>
                              updateAction(index, { ...action, field: e.target.value })
                            }
                            placeholder="field"
                          />
                        ) : null}
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="space-y-3 p-5">
                  <p className="text-xs text-muted-foreground">
                    This is exactly what gets stored and run (restricted DSL JSON — not PHP).
                  </p>
                  <pre className="max-h-[520px] overflow-auto rounded-lg border bg-slate-950 p-4 text-xs text-slate-100">
                    {codePreview}
                  </pre>
                </div>
              )}
            </section>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}

export function DynEntityHookEditorPageClient() {
  return (
    <Suspense fallback={<div className="p-8 text-sm text-muted-foreground">Loading…</div>}>
      <DynEntityHookEditorInner />
    </Suspense>
  );
}
