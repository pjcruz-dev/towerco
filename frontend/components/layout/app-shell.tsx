"use client";

import { useEffect, useMemo } from "react";

import { AppHeader } from "@/components/layout/app-header";
import { AppFooter } from "@/components/layout/app-footer";
import { GlobalCommandPalette } from "@/components/layout/global-command-palette";
import { SubscriptionAccessBanner } from "@/components/feedback/subscription-access-banner";
import { ImpersonationBanner } from "@/components/layout/impersonation-banner";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { AppTopNav } from "@/components/layout/app-top-nav";
import { AssistantDrawer } from "@/components/assistant/assistant-drawer";
import { AssistantFloatingLauncher } from "@/components/assistant/assistant-floating-launcher";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { useTenantNotificationRealtime } from "@/hooks/use-tenant-notification-realtime";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { GlobalCommandPaletteProvider } from "@/hooks/use-global-command-palette";
import { AssistantDrawerProvider } from "@/hooks/use-assistant-drawer";
import {
  isTenantModuleEnabled,
  resolveEnabledModulesForUser,
} from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";
import { useNavigationLayoutStore } from "@/stores/navigation-layout-store";

export function AppShell({ children }: { children: React.ReactNode }) {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const layout = useNavigationLayoutStore((s) => s.layout);
  const hydrateLayout = useNavigationLayoutStore((s) => s.hydrate);

  useEffect(() => {
    hydrateLayout();
  }, [hydrateLayout]);

  const scopedUser = useMemo(() => {
    return user && activeTenantId
      ? { ...user, permissions: effectivePermissions() }
      : user;
  }, [activeTenantId, effectivePermissions, user]);

  const canReceiveNotifications = useMemo(() => {
    return hasPermission(scopedUser, [permissions.eApprovalView]);
  }, [scopedUser]);

  const canUseAssistant = useMemo(() => {
    if (!hasPermission(scopedUser, [permissions.aiAssistantUse])) {
      return false;
    }

    const enabledModules = resolveEnabledModulesForUser(user, activeTenantId);
    // Fail closed: AI Assistant is opt-in and independent of Dynamic Entities.
    return isTenantModuleEnabled(enabledModules, "ai_assistant");
  }, [activeTenantId, scopedUser, user]);

  useTenantNotificationRealtime(canReceiveNotifications);

  const useNavbar = layout === "navbar";

  return (
    <GlobalCommandPaletteProvider>
      <AssistantDrawerProvider enabled={canUseAssistant}>
        <SidebarProvider>
          <div className="flex h-screen w-full overflow-hidden bg-background text-foreground antialiased print:h-auto print:overflow-visible">
            {useNavbar ? null : <AppSidebar />}
            <SidebarInset className="flex flex-1 flex-col overflow-hidden bg-transparent print:overflow-visible">
              {/* Navbar layout: light utility bar → dark module nav (Metacoresoft-style; no global + New). */}
              <AppHeader showSidebarTrigger={!useNavbar} showBrand={useNavbar} />
              {useNavbar ? <AppTopNav /> : null}
              <div className="print:hidden">
                <SubscriptionAccessBanner />
                <ImpersonationBanner />
              </div>
              <main className="scrollbar-hide flex-1 overflow-y-auto p-6 lg:p-8 print:h-auto print:overflow-visible print:p-0">
                <div className="mx-auto max-w-[min(100%,1920px)] print:max-w-none">{children}</div>
              </main>
              <AppFooter />
            </SidebarInset>
          </div>
          <GlobalCommandPalette />
          {canUseAssistant ? (
            <>
              <AssistantFloatingLauncher />
              <AssistantDrawer />
            </>
          ) : null}
        </SidebarProvider>
      </AssistantDrawerProvider>
    </GlobalCommandPaletteProvider>
  );
}
