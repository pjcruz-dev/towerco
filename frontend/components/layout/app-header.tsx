"use client";

import { useMemo } from "react";

import { AppHeaderSearchTrigger } from "@/components/layout/app-header-search-trigger";
import { UserProfileMenu } from "@/components/layout/user-profile-menu";
import { WorkspaceBreadcrumbs } from "@/components/layout/workspace-breadcrumbs";
import { TenantNotificationBell } from "@/components/notifications/tenant-notification-bell";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { Suspense } from "react";

export function AppHeader() {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);

  const scopedUser = useMemo(() => {
    if (!user || !activeTenantId) {
      return user;
    }
    return { ...user, permissions: effectivePermissions() };
  }, [activeTenantId, effectivePermissions, user]);

  const canViewNotifications = useMemo(
    () =>
      hasPermission(scopedUser, [permissions.eApprovalView]) ||
      hasPermission(scopedUser, [permissions.rolloutView]) ||
      hasPermission(scopedUser, [permissions.rolloutGateApprove]),
    [scopedUser],
  );

  return (
    <header className="sticky top-0 z-50 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-card px-4 sm:gap-3 sm:px-6 md:px-8 print:hidden">
      <SidebarTrigger
        data-help="ea-sidebar-trigger"
        className="-ml-1 shrink-0 text-muted-foreground hover:text-foreground"
      />
      <WorkspaceBreadcrumbs />
      <div className="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
        <AppHeaderSearchTrigger />
        {canViewNotifications ? <TenantNotificationBell /> : null}
        <Suspense fallback={null}>
          <UserProfileMenu />
        </Suspense>
      </div>
    </header>
  );
}
