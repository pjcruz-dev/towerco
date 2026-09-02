"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  createDynFieldGroup,
  fetchDynEntities,
  fetchDynEntity,
  updateDynField,
  type DynEntityDetail,
  type DynEntitySummary,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";

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
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Field Groups</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Organize how fields appear on forms and record views. Fields left ungrouped render above
              groups — an entity with no groups is unaffected.
            </p>
          </div>
          <Button variant="outline" size="sm" render={<Link href="/dynamic-entities/fields" />}>
            Manage Fields
          </Button>
        </header>

        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3">
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Entity</span>
            <select
              className="flex h-9 min-w-[220px] rounded-md border border-input bg-background px-3 text-sm"
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
            >
              {entities.map((e) => (
                <option key={e.id} value={e.slug}>
                  {e.name}
                </option>
              ))}
            </select>
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
                  <select
                    className="h-8 rounded-md border border-input bg-background px-2 text-xs"
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
                  </select>
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
