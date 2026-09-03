"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";

import { DynRelationshipPicker } from "@/components/dynamic-entities/dyn-relationship-field";
import {
  DynSchemaSheets,
  type DynSchemaMode,
} from "@/components/dynamic-entities/dyn-schema-sheets";
import { DynStructuredLayout } from "@/components/dynamic-entities/dyn-structured-layout";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import {
  createDynRecord,
  fetchDynEntity,
  type DynEntityDetail,
  type DynField,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  canEntityAction,
  isFieldHiddenForRole,
  isFieldReadOnlyForRole,
  notifyDynPermissionDenied,
} from "@/lib/rbac/entity-access";
import { parseDynSelectOptions } from "@/lib/dynamic-entities/select-choices";
import { parseDynNumberOptions } from "@/lib/dynamic-entities/number-options";
import { parseDynTextOptions } from "@/lib/dynamic-entities/text-options";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

export function DynamicEntityNewRecordPageClient({ slug }: { slug: string }) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const parentRecordId = searchParams.get("parent_record_id");
  const foreignField = searchParams.get("foreign_field");
  const returnTo = searchParams.get("return_to");
  const canManageSchema = usePermission([permissions.dynamicEntitiesFieldsManage]);
  const canManageRecords = usePermission([permissions.dynamicEntitiesRecordsManage]);
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const canCreate = canManageRecords && canEntityAction(accessMatrix, slug, "create");
  const [entity, setEntity] = useState<DynEntityDetail | null>(null);
  const [values, setValues] = useState<Record<string, string>>(() => {
    if (parentRecordId && foreignField) {
      return { [foreignField]: parentRecordId };
    }
    return {};
  });
  const [status, setStatus] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [schemaMode, setSchemaMode] = useState<DynSchemaMode>({ kind: "closed" });

  async function reloadEntity() {
    const row = await fetchDynEntity(slug);
    setEntity(row);
    return row;
  }

  useEffect(() => {
    if (!canCreate) {
      notifyDynPermissionDenied({
        action: "create",
        entityLabel: slug.replace(/_/g, " "),
      });
      router.replace(`/dynamic-entities/${slug}`);
    }
  }, [canCreate, router, slug]);

  useEffect(() => {
    let cancelled = false;
    fetchDynEntity(slug)
      .then((row) => {
        if (cancelled) return;
        setEntity(row);
        setValues((prev) => {
          const next = { ...prev };
          let changed = false;
          for (const field of row.fields ?? []) {
            if (next[field.name]) continue;
            if (field.type === "select" || field.type === "multiselect") {
              const def = parseDynSelectOptions(field.options).default;
              if (def) {
                next[field.name] = def;
                changed = true;
              }
              continue;
            }
            if (field.type === "number" || field.type === "decimal") {
              const def = parseDynNumberOptions(field.options).default_value;
              if (def) {
                next[field.name] = def;
                changed = true;
              }
              continue;
            }
            if (field.type === "text" || field.type === "textarea" || field.type === "email") {
              const def = parseDynTextOptions(field.options).default_value;
              if (def) {
                next[field.name] = def;
                changed = true;
              }
            }
          }
          return changed ? next : prev;
        });
      })
      .catch((err) => {
        if (!cancelled) {
          notifyDynPermissionDenied({
            action: "view",
            entityLabel: slug.replace(/_/g, " "),
            error: err,
          });
          setError(null);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  useEffect(() => {
    if (!parentRecordId || !foreignField) return;
    setValues((prev) =>
      prev[foreignField] === parentRecordId
        ? prev
        : { ...prev, [foreignField]: parentRecordId },
    );
  }, [foreignField, parentRecordId]);

  const groups = useMemo(
    () => [...(entity?.field_groups ?? [])].filter((g) => g.applies_to_form).sort((a, b) => a.sort_order - b.sort_order),
    [entity],
  );

  const fields = useMemo(
    () =>
      [...(entity?.fields ?? [])]
        .filter(
          (f) =>
            !f.is_system_field &&
            !["actions", "workflows", "print", "id"].includes(f.name) &&
            f.type !== "file" &&
            !isFieldHiddenForRole(accessMatrix, slug, f.name) &&
            !isFieldReadOnlyForRole(accessMatrix, slug, f.name),
        )
        .sort((a, b) => a.field_order - b.field_order),
    [entity, accessMatrix, slug],
  );

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!entity) return;
    if (!canCreate) {
      notifyDynPermissionDenied({ action: "create", entityLabel: entity.name });
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const payloadValues: Record<string, unknown> = {};
      for (const field of fields) {
        const raw = values[field.name];
        if (raw === undefined || raw === "") continue;
        if (field.type === "number" || field.type === "decimal") {
          payloadValues[field.name] = Number(raw);
        } else if (field.type === "boolean") {
          payloadValues[field.name] = raw === "true" || raw === "1";
        } else {
          payloadValues[field.name] = raw;
        }
      }
      if (parentRecordId && foreignField && !payloadValues[foreignField]) {
        payloadValues[foreignField] = parentRecordId;
      }
      const record = await createDynRecord(slug, {
        status: status || undefined,
        values: payloadValues,
        parent_record_id: parentRecordId || undefined,
      });
      router.push(
        returnTo && returnTo.startsWith("/") && !returnTo.startsWith("//")
          ? returnTo
          : `/dynamic-entities/records/${record.id}`,
      );
    } catch (err) {
      notifyDynPermissionDenied({ action: "create", entityLabel: entity.name, error: err });
      const msg = getErrorMessage(err);
      if (!/does not allow|access denied|forbidden/i.test(msg)) {
        setError(msg);
      }
    } finally {
      setSaving(false);
    }
  }

  function patchValue(fieldName: string, next: string) {
    setValues((prev) => (prev[fieldName] === next ? prev : { ...prev, [fieldName]: next }));
  }

  function renderFormField(field: DynField) {
    if (field.type === "relationship") {
      return (
        <DynRelationshipPicker
          field={field}
          value={values[field.name] ?? ""}
          onChange={(next) => patchValue(field.name, next)}
          required={field.is_required}
        />
      );
    }
    if (field.type === "select" || field.type === "multiselect") {
      const choices = parseDynSelectOptions(field.options).choices;
      return (
        <SelectField
          value={values[field.name] ?? ""}
          onChange={(next) => patchValue(field.name, next)}
          options={choices.map((c) => ({ value: c.value, label: c.label }))}
          allowEmpty={!field.is_required}
          placeholder="Select…"
        />
      );
    }
    if (field.type === "textarea") {
      return (
        <Textarea
          className="min-h-20"
          value={values[field.name] ?? ""}
          onChange={(e) => patchValue(field.name, e.target.value)}
          required={field.is_required}
        />
      );
    }
    if (field.type === "boolean") {
      return (
        <label className="inline-flex h-9 items-center gap-2 text-sm">
          <Checkbox
            checked={values[field.name] === "true" || values[field.name] === "1"}
            onCheckedChange={(checked) =>
              patchValue(field.name, checked === true ? "true" : "false")
            }
          />
          <span className="text-muted-foreground">
            {values[field.name] === "true" || values[field.name] === "1" ? "Yes" : "No"}
          </span>
        </label>
      );
    }
    if (field.type === "date") {
      return (
        <DatePicker
          value={values[field.name] ?? ""}
          onChange={(next) => patchValue(field.name, next)}
        />
      );
    }
    return (
      <Input
        type={
          field.type === "number" || field.type === "decimal"
            ? "number"
            : field.type === "datetime"
              ? "datetime-local"
              : "text"
        }
        value={values[field.name] ?? ""}
        onChange={(e) => patchValue(field.name, e.target.value)}
        required={field.is_required}
      />
    );
  }

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesRecordsManage]}>
      <div className="w-full space-y-5">
        <header>
          <p className="text-sm text-muted-foreground">
            <Link href={`/dynamic-entities/${slug}`} className="underline-offset-4 hover:underline">
              {entity?.name ?? slug}
            </Link>
            {" / New"}
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">New {entity?.name ?? "record"}</h1>
        </header>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <form onSubmit={onSubmit} className="space-y-4">
          <Card className="rounded-xl">
            <CardHeader>
              <CardTitle className="text-base font-medium">Status</CardTitle>
            </CardHeader>
            <CardContent>
              <Input value={status} onChange={(e) => setStatus(e.target.value)} placeholder="Optional status" />
            </CardContent>
          </Card>

          <DynStructuredLayout
            groups={groups}
            fields={fields}
            canManageSchema={canManageSchema}
            mode="form"
            renderFieldValue={renderFormField}
            onSchemaAction={setSchemaMode}
            onLayoutChanged={async () => {
              await reloadEntity();
            }}
          />

          <div className="flex gap-2">
            <Button type="submit" disabled={saving || !entity}>
              {saving ? "Saving…" : "Create"}
            </Button>
            <Button
              type="button"
              variant="outline"
              render={
                <Link
                  href={
                    returnTo && returnTo.startsWith("/") && !returnTo.startsWith("//")
                      ? returnTo
                      : `/dynamic-entities/${slug}`
                  }
                  prefetch={false}
                />
              }
            >
              Cancel
            </Button>
          </div>
        </form>

        {entity ? (
          <DynSchemaSheets
            entitySlug={slug}
            entityName={entity.name}
            groups={entity.field_groups}
            fields={entity.fields}
            mode={schemaMode}
            onClose={() => setSchemaMode({ kind: "closed" })}
            onSaved={async () => {
              await reloadEntity();
            }}
          />
        ) : null}
      </div>
    </PermissionGate>
  );
}
