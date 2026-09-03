"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import {
  createDynFieldGroup,
  fetchDynEntities,
  fetchDynEntity,
  updateDynField,
  type DynEntityDetail,
  type DynEntitySummary,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";

export function DynamicFieldGroupsPageClient() {
  const searchParams = useSearchParams();
  const initialSlug = searchParams.get("entity") ?? "";
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [slug, setSlug] = useState(initialSlug);
  const [detail, setDetail] = useState<DynEntityDetail | null>(null);
  const [groupName, setGroupName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchDynEntities({ active_only: false })
      .then((rows) => {
        setEntities(rows);
        if (!initialSlug && rows[0]) setSlug(rows[0].slug);
      })
      .catch(() => setError("Unable to load entities."));
  }, [initialSlug]);

  useEffect(() => {
    if (!slug) {
      setDetail(null);
      return;
    }
    let cancelled = false;
    fetchDynEntity(slug)
      .then((row) => {
        if (!cancelled) {
          setDetail(row);
          setError(null);
        }
      })
      .catch(() => {
        if (!cancelled) setError("Unable to load field groups.");
      });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  const ungrouped = useMemo(
    () => (detail?.fields ?? []).filter((f) => !f.form_group_id),
    [detail],
  );

  async function refresh() {
    if (!slug) return;
    setDetail(await fetchDynEntity(slug));
  }

  async function onCreateGroup() {
    if (!slug || !groupName.trim()) return;
    setSaving(true);
    try {
      await createDynFieldGroup(slug, { name: groupName.trim() });
      setGroupName("");
      await refresh();
    } catch {
      setError("Unable to create group.");
    } finally {
      setSaving(false);
    }
  }

  async function moveField(fieldId: string, groupId: string | null) {
    setSaving(true);
    try {
      await updateDynField(fieldId, { form_group_id: groupId, view_group_id: groupId });
      await refresh();
    } catch {
      setError("Unable to assign field.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesFieldsManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Field Groups"
          description="Organize how fields appear on forms and record views. Fields left ungrouped render above groups — an entity with no groups is unaffected."
          actions={
            <Button variant="outline" size="sm" render={<Link href="/dynamic-entities/fields" />}>
              Manage Fields
            </Button>
          }
        />

        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3">
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Entity</span>
            <Select
              className="min-w-[220px]"
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
            >
              {entities.map((e) => (
                <option key={e.id} value={e.slug}>
                  {e.name}
                </option>
              ))}
            </Select>
          </label>
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">New group</span>
            <Input
              className="h-9 w-56"
              value={groupName}
              onChange={(e) => setGroupName(e.target.value)}
              placeholder="Basic Information"
            />
          </label>
          <Button size="sm" disabled={saving || !groupName.trim()} onClick={() => void onCreateGroup()}>
            + New Group
          </Button>
        </div>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <div className="grid gap-4 lg:grid-cols-2">
          <Card className="rounded-xl">
            <CardHeader>
              <CardTitle className="text-base font-medium">Ungrouped</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {ungrouped.map((field) => (
                <div
                  key={field.id}
                  className="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2"
                >
                  <div>
                    <div className="text-sm font-medium">{field.label}</div>
                    <div className="text-xs text-muted-foreground">
                      {field.name}: {field.type}
                    </div>
                  </div>
                  <Select
                    className="h-8 w-36 text-xs"
                    defaultValue=""
                    disabled={saving || (detail?.field_groups.length ?? 0) === 0}
                    onChange={(e) => {
                      const v = e.target.value;
                      if (v) void moveField(field.id, v);
                    }}
                  >
                    <option value="">Move to…</option>
                    {(detail?.field_groups ?? []).map((g) => (
                      <option key={g.id} value={g.id}>
                        {g.name}
                      </option>
                    ))}
                  </Select>
                </div>
              ))}
              {ungrouped.length === 0 ? (
                <p className="text-sm text-muted-foreground">All fields are assigned to groups.</p>
              ) : null}
            </CardContent>
          </Card>

          <div className="space-y-4">
            {(detail?.field_groups ?? []).map((group) => {
              const groupFields = (detail?.fields ?? []).filter((f) => f.form_group_id === group.id);
              return (
                <Card key={group.id} className="rounded-xl">
                  <CardHeader>
                    <CardTitle className="text-base font-medium">{group.name}</CardTitle>
                    <p className="text-xs text-muted-foreground">
                      Shown on:{" "}
                      {[group.applies_to_form ? "Form" : null, group.applies_to_view ? "Record View" : null]
                        .filter(Boolean)
                        .join(" & ") || "—"}
                    </p>
                  </CardHeader>
                  <CardContent className="space-y-2">
                    {groupFields.map((field) => (
                      <div
                        key={field.id}
                        className="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2"
                      >
                        <div>
                          <div className="text-sm font-medium">{field.label}</div>
                          <div className="text-xs text-muted-foreground">
                            {field.name}: {field.type}
                          </div>
                        </div>
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={saving}
                          onClick={() => void moveField(field.id, null)}
                        >
                          Ungroup
                        </Button>
                      </div>
                    ))}
                    {groupFields.length === 0 ? (
                      <p className="text-sm text-muted-foreground">Drop fields here via Move to…</p>
                    ) : null}
                  </CardContent>
                </Card>
              );
            })}
            {(detail?.field_groups.length ?? 0) === 0 ? (
              <p className="text-sm text-muted-foreground">Create a group to start organizing fields.</p>
            ) : null}
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}
