"use client";

import Link from "next/link";

import { ControlledDocumentRegisterAccessCard } from "@/components/documents/controlled-document-register-access-card";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export function ControlledDocumentAccessPageClient() {
  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledManage]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Register access"
          description={
            <Link
              href="/documents/controlled/register"
              className="font-medium text-foreground underline-offset-2 hover:underline"
            >
              Back to register
            </Link>
          }
        />
        <ControlledDocumentRegisterAccessCard startOpen />
      </div>
    </PermissionGate>
  );
}
