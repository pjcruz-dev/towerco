import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function IotMonitoringPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">IoT Monitoring</h1>
        <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
          Device telemetry and Timescale-backed series views will appear here.
        </p>
      </div>
    </PermissionGate>
  );
}
