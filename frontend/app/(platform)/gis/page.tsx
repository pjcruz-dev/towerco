import { OperationalMapPanel } from "@/components/gis/operational-map-panel";
import { PermissionGate } from "@/components/layout/permission-gate";
import { permissions } from "@/lib/rbac/permissions";

export default function GisPage() {
  return (
    <PermissionGate requiredPermissions={[permissions.gisView]}>
      <div className="space-y-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">GIS Network View</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Operational map surface (MapLibre GL). Overlays and tenant feeds connect here.
          </p>
        </div>

        <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
          <aside className="rounded-lg border border-border bg-card p-4">
            <h2 className="mb-3 text-sm font-semibold">Layers</h2>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li>Sites</li>
              <li>Fiber routes</li>
              <li>Outages</li>
              <li>Maintenance zones</li>
            </ul>
          </aside>

          <OperationalMapPanel />
        </div>
      </div>
    </PermissionGate>
  );
}
