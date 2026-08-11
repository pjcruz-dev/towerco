"use client";

import Link from "next/link";
import { LifeBuoy } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import { buildRaiseTicketUrl, type RaiseTicketPrefill } from "@/lib/ticketing/raise-ticket";
import { permissions } from "@/lib/rbac/permissions";
import { isTenantModuleEnabled, resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";

type Props = {
  prefill: RaiseTicketPrefill;
  size?: "sm" | "default";
  variant?: "outline" | "default" | "ghost";
  className?: string;
};

export function RaiseTicketButton({
  prefill,
  size = "sm",
  variant = "outline",
  className,
}: Props) {
  const user = useAuthStore((s) => s.user);
  const activeTenantId = useAuthStore((s) => s.activeTenantId);
  const enabledModules = resolveEnabledModulesForUser(user, activeTenantId);
  const canCreate = usePermission([permissions.ticketingTicketsCreate]);
  const ticketingEnabled = isTenantModuleEnabled(enabledModules, "ticketing");

  if (!ticketingEnabled || !canCreate) {
    return null;
  }

  return (
    <Button size={size} variant={variant} className={className} render={<Link href={buildRaiseTicketUrl(prefill)} />}>
      <LifeBuoy className="h-3.5 w-3.5" aria-hidden />
      Raise ticket
    </Button>
  );
}
