"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { getErrorMessage } from "@/lib/api/error";
import { platformPatchTenantSettings, type PlatformTenantRow } from "@/lib/api/modules/platform-api";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  tenant: PlatformTenantRow;
};

const accessOptions = [
  { value: "", label: "Normal (billing-driven)" },
  { value: "read_only", label: "Read-only — view data, block writes" },
  { value: "blocked", label: "Suspended — block API access" },
] as const;

function accessModeLabel(mode: string | null | undefined): string {
  if (mode === "read_only") return "Read-only";
  if (mode === "blocked") return "Suspended";
  if (mode === "grace") return "Past due (grace)";
  return "Full access";
}

export function TenantOperatorAccessCard({ tenant }: Props) {
  const user = usePlatformAuthStore((s) => s.user);
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();

  const canManage = platformHasPermission(user, PLATFORM_PERMS.tenantsManage);
  const currentOverride = tenant.operator_access_mode ?? "";
  const effectiveMode = tenant.access_mode ?? "full";

  const mutation = useMutation({
    mutationFn: (operatorAccessMode: string | null) =>
      platformPatchTenantSettings(tenant.id, {
        operator_access_mode: operatorAccessMode,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id, "audit"] });
      notify({ level: "success", title: "Access updated", message: "Operator access mode saved." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not update access", message: getErrorMessage(error) }),
  });

  if (!canManage) {
    return (
      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Operator access</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          Effective mode: <Badge variant="outline">{accessModeLabel(effectiveMode)}</Badge>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="rounded-xl shadow-sm">
      <CardHeader>
        <CardTitle className="text-base font-medium">Operator access</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <p className="text-muted-foreground">
          Override tenant access for support or compliance. Billing subscription rules still apply unless
          you choose an operator override below.
        </p>
        <p>
          Effective mode:{" "}
          <Badge variant="outline" className="font-normal">
            {accessModeLabel(effectiveMode)}
          </Badge>
          {tenant.operator_access_mode ? (
            <span className="ml-2 text-xs text-muted-foreground">
              (operator: {tenant.operator_access_mode.replace("_", " ")})
            </span>
          ) : null}
        </p>
        <div className="grid gap-2 sm:max-w-md">
          <Label htmlFor="operator-access-mode">Operator override</Label>
          <Select
            id="operator-access-mode"
            className="h-9"
            value={currentOverride}
            disabled={mutation.isPending}
            onChange={(event) => {
              const value = event.target.value;
              mutation.mutate(value === "" ? null : value);
            }}
          >
            {accessOptions.map((option) => (
              <option key={option.value || "normal"} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={mutation.isPending || currentOverride === ""}
          onClick={() => mutation.mutate(null)}
        >
          Clear operator override
        </Button>
      </CardContent>
    </Card>
  );
}
