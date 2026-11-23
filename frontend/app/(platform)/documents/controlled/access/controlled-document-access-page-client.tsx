"use client";

import Link from "next/link";

import { ControlledDocumentRegisterAccessCard } from "@/components/documents/controlled-document-register-access-card";
import { useDocumentRegisterRolePreview } from "@/components/documents/document-register-role-field-access";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export function ControlledDocumentAccessPageClient() {
  const preview = useDocumentRegisterRolePreview();
  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledManage]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Register access"
          description={
            {preview.allowsHref("/documents/controlled/register") ? (
            <Link
              href="/documents/controlled/register"
              className="font-medium text-foreground underline-offset-2 hover:underline"
            >
              Back to register
            </Link>
            ) : null}
          }
        />
        <ControlledDocumentRegisterAccessCard startOpen />
      </div>
    </PermissionGate>
  );
}
