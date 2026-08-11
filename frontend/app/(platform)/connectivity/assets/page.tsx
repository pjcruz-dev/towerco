import { AssetAuditView } from "@/components/connectivity/asset-audit-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function AssetAuditPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <AssetAuditView />
    </PermissionGate>
  );
}
