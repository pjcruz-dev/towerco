import { TenantWorkspaceDashboard } from "@/components/dashboard/tenant-workspace-dashboard";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function DashboardPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <TenantWorkspaceDashboard />
    </PermissionGate>
  );
}
