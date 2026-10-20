"use client";

import { useQuery } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { AssetFieldInput, buildAssetWriteBody } from "@/app/(platform)/assets/asset-field-control";
import { DynStructuredLayout } from "@/components/dynamic-entities/dyn-structured-layout";
import {
  RequiredFieldsAlert,
  useRequiredFields,
} from "@/components/dynamic-entities/required-fields-alert";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { createAsset, fetchAssetSchema } from "@/lib/api/modules/assets-api";
import { fetchDynEntity } from "@/lib/api/modules/dynamic-entities-api";
import { fieldsForSurface, groupsForSurface, OVERVIEW_SURFACE } from "@/lib/dynamic-entities/form-surfaces";
import { getErrorMessage } from "@/lib/api/error";
import { isFieldHiddenOnSurface, isFieldReadOnlyForRole } from "@/lib/rbac/entity-access";
import { notify } from "@/lib/ui/notify";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { useAuthStore } from "@/stores/auth-store";

const emptyDraft = { asset_type: "laptop", status: "AVAILABLE" };

export function AssetRegisterPageClient() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const permissions =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId)?.permissions ??
    user?.permissions ??
    [];
  const canManage = permissions.includes("assets:manage");
  const canManageFields = permissions.includes("assets:fields:manage");
  const accessMatrix = user?.accessMatrix;
  const entity = useQuery({
    queryKey: ["it-asset-entity"],
    queryFn: async () => {
      await fetchAssetSchema();
      return fetchDynEntity("it_asset");
    },
    staleTime: 0,
    refetchOnMount: "always",
  });
  const visibleFields = (entity.data?.fields ?? []).filter(
    (field) => canManageFields || !isFieldHiddenOnSurface(accessMatrix, "it_asset", field.name, "form"),
  );
  const registerGroups = groupsForSurface(entity.data?.field_groups ?? [], OVERVIEW_SURFACE);
  const fields = fieldsForSurface(visibleFields, entity.data?.field_groups ?? [], OVERVIEW_SURFACE);
  const [draft, setDraft] = useState<Record<string, string>>(emptyDraft);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const required = useRequiredFields(fields, draft);

  async function onSave() {
    if (required.blockIfMissing()) return;
    setSaving(true);
    setError(null);
    try {
      const writable = fields.filter((field) => !isFieldReadOnlyForRole(accessMatrix, "it_asset", field.name));
      const created = await createAsset(buildAssetWriteBody(writable, draft));
      notify.success("Asset registered", created.asset_tag);
      router.push(`/assets/${created.asset_tag}`);
    } catch (caught: unknown) {
      setError(getErrorMessage(caught));
    } finally {
      setSaving(false);
    }
  }

  if (!canManage) {
    return (
      <div className={adminPageShellClass}>
        <p className="text-sm">You do not have permission to register an asset.</p>
        <Button type="button" className="mt-3" variant="outline" render={<Link href="/assets" />}>
          Back
        </Button>
      </div>
    );
  }

  return (
    <div className={adminPageShellClass}>
      <WorkspacePageHeader
        title="Register asset"
        description="The tag is issued on save. A new asset starts as New or Available."
        actions={
          <>
            <Button type="button" size="sm" variant="outline" render={<Link href="/assets" />}>
              <ArrowLeft className="size-3.5" />
              Back
            </Button>
            <Button type="button" size="sm" disabled={saving || !entity.data} onClick={() => void onSave()}>
              {saving ? "Saving…" : "Save"}
            </Button>
          </>
        }
      />
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <RequiredFieldsAlert fields={required.missing} pulse={required.pulse} />
      <section className="min-w-0 space-y-4">
        {entity.data ? (
          <DynStructuredLayout
            groups={registerGroups}
            fields={fields}
            entitySlug="it_asset"
            canManageSchema={canManageFields}
            layoutEditing={false}
            allowWidthAdjust={false}
            mode="form"
            invalidFieldNames={required.invalidNames}
            renderFieldValue={(field) =>
              !canManageFields && isFieldReadOnlyForRole(accessMatrix, "it_asset", field.name) ? (
                <span className="text-muted-foreground">{draft[field.name] || "—"}</span>
              ) : (
                <AssetFieldInput
                  field={field}
                  value={draft[field.name] ?? ""}
                  onChange={(next) => setDraft((current) => ({ ...current, [field.name]: next }))}
                  mode="create"
                />
              )
            }
            onSchemaAction={() => undefined}
            onLayoutChanged={() => undefined}
          />
        ) : (
          <div className="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground shadow-sm">
            {entity.isError ? getErrorMessage(entity.error) : "Loading fields…"}
          </div>
        )}
      </section>
    </div>
  );
}
