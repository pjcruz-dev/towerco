import { TowerInventoryView } from "@/components/connectivity/tower-inventory-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function TowerInventoryPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <TowerInventoryView />
    </PermissionGate>
  );
}
