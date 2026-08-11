import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function SpectrumPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Spectrum management</h1>
        <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
          Allocation planning and interference analysis will appear here.
        </p>
      </div>
    </PermissionGate>
  );
}
