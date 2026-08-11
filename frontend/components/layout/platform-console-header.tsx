"use client";

import { ChevronRight } from "lucide-react";
import { usePathname } from "next/navigation";
import { useMemo } from "react";

import { PlatformUserProfileMenu } from "@/components/layout/platform-user-profile-menu";
import { SidebarTrigger } from "@/components/ui/sidebar";

const segmentLabels: Record<string, string> = {
  platform: "Dashboard",
  playbooks: "Rollout playbooks",
  tenants: "Tenants",
  create: "Create tenant",
};

export function PlatformConsoleHeader() {
  const pathname = usePathname();

  const pageTitle = useMemo(() => {
    const parts = pathname.split("/").filter(Boolean);
    if (parts.length <= 1) {
      return "Dashboard";
    }
    if (parts[1] === "playbooks") {
      if (parts[2] === "policies" && parts[3]) {
        return "Rollout policy editor";
      }
      return "Rollout playbooks";
    }
    if (parts[1] === "tenants" && parts[2] === "create") {
      return "Create tenant";
    }
    const last = parts[parts.length - 1];
    return segmentLabels[last] ?? last.replace(/-/g, " ");
  }, [pathname]);

  return (
    <header className="sticky top-0 z-50 flex h-16 items-center gap-4 border-b border-border bg-card px-6 backdrop-blur-sm md:px-8">
      <SidebarTrigger className="-ml-1 text-muted-foreground hover:text-foreground" />

      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <span>Superadmin</span>
        <ChevronRight className="h-3 w-3 shrink-0 opacity-70" />
        <span className="font-medium capitalize text-foreground">{pageTitle}</span>
      </div>

      <div className="ml-auto flex flex-1 items-center justify-end gap-3">
        <PlatformUserProfileMenu />
      </div>
    </header>
  );
}
