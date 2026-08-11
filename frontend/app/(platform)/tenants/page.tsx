import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function TenantsPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.tenantManage]}>
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Tenants</h1>
        <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
          Landlord onboarding and domain management will appear here.
        </p>
      </div>
    </PermissionGate>
  );
}
