"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft, Save } from "lucide-react";

import { DynWorkflowButtonsEditor } from "@/components/dynamic-entities/dyn-workflow-buttons-editor";
import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";
import {
  fetchDynEntities,
  fetchDynEntity,
  fetchDynWorkflow,
  updateDynWorkflow,
  type DynEntitySummary,
  type DynWorkflowRow,
  type DynWorkflowTriggerMode,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  newEmptyWorkflowButton,
  normalizeWorkflowButton,
  type DynWorkflowActionDef,
} from "@/lib/dynamic-entities/dyn-workflow-actions";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";

function DynWorkflowEditorInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const id = searchParams.get("id") ?? "";

  const [workflow, setWorkflow] = useState<DynWorkflowRow | null>(null);
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [roles, setRoles] = useState<Array<{ id: string; name: string }>>([]);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [entitySlug, setEntitySlug] = useState("");
  const [triggerMode, setTriggerMode] = useState<DynWorkflowTriggerMode>("manual");
  const [statusField, setStatusField] = useState("status");
  const [statusMatches, setStatusMatches] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [buttons, setButtons] = useState<DynWorkflowActionDef[]>([]);
  const [fieldOptions, setFieldOptions] = useState<
    Array<{ name: string; label: string; type?: string; options?: unknown; target_entity_slug?: string | null }>
  >([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) {
      setError("Missing workflow id.");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [row, ents, catalog] = await Promise.all([
        fetchDynWorkflow(id),
        fetchDynEntities({ active_only: true }),
        fetchAdminRoleCatalog().catch(() => null),
      ]);
      setWorkflow(row);
      setEntities(ents);
      setName(row.name);
      setDescription(row.description ?? "");
      setEntitySlug(row.entity_slug);
      setTriggerMode(row.trigger_mode);
      setStatusField(row.status_field || "status");
      setStatusMatches((row.status_matches ?? []).join(", "));
      setIsActive(row.is_active);
      const def = (row.definition_json ?? row.action ?? {}) as Record<string, unknown>;
      const normalized = normalizeWorkflowButton(
        { ...def, label: String(def.label ?? row.name), id: String(def.id ?? row.slug) },
        new Set<string>(),
      );
      setButtons([
        normalized ?? {
          ...newEmptyWorkflowButton(),
          id: row.slug,
          label: row.name,
          role_ids: row.role_ids ?? [],
          when:
            (row.status_matches ?? []).length > 0
              ? [
                  {
                    field: row.status_field || "status",
                    op: "eq",
                    value: row.status_matches[0],
                  },
                ]
              : [{ field: "status", op: "eq", value: "Draft" }],
        },
      ]);
      setRoles(
        (catalog?.roles ?? []).map((r) => ({ id: String(r.id), name: r.name })),
      );

      try {
        const detail = await fetchDynEntity(row.entity_slug);
        setFieldOptions(
          (detail.fields ?? []).map((f) => ({
            name: f.name,
            label: f.label || f.name,
            type: f.type,
            options: f.options,
            target_entity_slug: f.target_entity_slug,
          })),
        );
      } catch {
        setFieldOptions([]);
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!entitySlug || entitySlug === workflow?.entity_slug) return;
    let cancelled = false;
    void (async () => {
      try {
        const detail = await fetchDynEntity(entitySlug);
        if (!cancelled) {
          setFieldOptions(
            (detail.fields ?? []).map((f) => ({
              name: f.name,
              label: f.label || f.name,
              type: f.type,
              options: f.options,
              target_entity_slug: f.target_entity_slug,
            })),
          );
        }
      } catch {
        if (!cancelled) setFieldOptions([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [entitySlug, workflow?.entity_slug]);

  const entityOptions = useMemo(
    () => entities.map((e) => ({ slug: e.slug, name: e.name })),
    [entities],
  );

  async function onSave() {
    if (!id) return;
    setSaving(true);
    setError(null);
    try {
      const primary = buttons[0];
      const definition_json = primary
        ? {
            ...primary,
            label: name.trim() || primary.label,
            role_ids: primary.role_ids ?? [],
          }
        : {};
      await updateDynWorkflow(id, {
        name: name.trim(),
        description: description.trim() || null,
        entity_slug: entitySlug,
        trigger_mode: triggerMode,
        status_field: statusField.trim() || "status",
        status_matches: statusMatches,
        role_ids: primary?.role_ids ?? [],
        is_active: isActive,
        definition_json,
      });
      router.push("/dynamic-entities/workflows");
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.workflowsManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Edit Workflow"
          description="Configure trigger constraints and THEN steps (updates, creates, emails)."
          actions={
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                render={<Link href="/dynamic-entities/workflows" />}
              >
                <ArrowLeft className="size-4" />
                Back
              </Button>
              <Button type="button" size="sm" disabled={saving || loading} onClick={() => void onSave()}>
                <Save className="size-4" />
                Save
              </Button>
            </>
          }
        />

        {error ? (
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : (
          <div className="space-y-4">
            <section className="space-y-3 rounded-xl border bg-card p-4 shadow-sm">
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Workflow Name</Label>
                  <Input value={name} onChange={(e) => setName(e.target.value)} />
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Description</Label>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    className="min-h-[72px]"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Target Entity</Label>
                  <Select
                    value={entitySlug}
                    onChange={(e) => setEntitySlug(e.target.value)}
                  >
                    {entities.map((e) => (
                      <option key={e.id} value={e.slug}>
                        {e.name}
                      </option>
                    ))}
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Trigger Mode</Label>
                  <Select
                    value={triggerMode}
                    onChange={(e) => setTriggerMode(e.target.value as DynWorkflowTriggerMode)}
                  >
                    <option value="manual">Manual Button (User-Triggered)</option>
                    <option value="on_create">Auto on Record Create</option>
                    <option value="on_update">Auto on Record Update</option>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Status Field Slug</Label>
                  <Input value={statusField} onChange={(e) => setStatusField(e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>When Status matches...</Label>
                  <Input
                    value={statusMatches}
                    onChange={(e) => setStatusMatches(e.target.value)}
                    placeholder="e.g. Draft, Pending"
                  />
                </div>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                  <Checkbox checked={isActive} onCheckedChange={(v) => setIsActive(v === true)} />
                  Active
                </label>
              </div>
            </section>

            <section className="rounded-xl border bg-card p-4 shadow-sm">
              <h2 className="text-base font-medium">Steps (THEN actions)</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Field updates, related loads, record creates, and emails. Manual workflows also
                appear as buttons on the record detail page.
              </p>
              <div className="mt-4">
                <DynWorkflowButtonsEditor
                  buttons={buttons}
                  onChange={(next) => setButtons(next.slice(0, 1))}
                  fieldOptions={fieldOptions}
                  roleOptions={roles}
                  entityOptions={entityOptions}
                />
              </div>
            </section>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}

export function DynWorkflowEditorPageClient() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading…</p>}>
      <DynWorkflowEditorInner />
    </Suspense>
  );
}
