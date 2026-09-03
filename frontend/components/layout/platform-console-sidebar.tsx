"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useMemo } from "react";
import { BookOpen, CircleHelp, CreditCard, LayoutGrid, Layers, LogIn, PlusCircle, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import { SidebarBrand } from "@/components/layout/sidebar-brand";

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { isNavActive } from "@/lib/navigation/is-nav-active";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";

type NavItem = {
  title: string;
  href: string;
  icon: LucideIcon;
  exact?: boolean;
  permission?: string;
};

const navItems: NavItem[] = [
  {
    title: "Dashboard",
    href: "/platform",
    icon: Layers,
    exact: true,
    permission: PLATFORM_PERMS.consoleView,
  },
  {
    title: "Billing",
    href: "/platform/billing",
    icon: CreditCard,
    permission: PLATFORM_PERMS.billingView,
  },
  {
    title: "Playbooks",
    href: "/platform/playbooks",
    icon: BookOpen,
    permission: PLATFORM_PERMS.playbooksView,
  },
  {
    title: "Operators",
    href: "/platform/operators",
    icon: Users,
    permission: PLATFORM_PERMS.operatorsView,
  },
  {
    title: "App Menu",
    href: "/platform/app-menu",
    icon: LayoutGrid,
    permission: PLATFORM_PERMS.tenantsManage,
  },
  {
    title: "Helper center",
    href: "/platform/helper-center",
    icon: CircleHelp,
    permission: PLATFORM_PERMS.consoleView,
  },
  {
    title: "Create tenant",
    href: "/platform/tenants/create",
    icon: PlusCircle,
    permission: PLATFORM_PERMS.tenantsManage,
  },
];

const navButtonClass =
  "text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-active:bg-sidebar-accent data-active:text-sidebar-accent-foreground";

export function PlatformConsoleSidebar() {
  const pathname = usePathname();
  const { isMobile, setOpenMobile } = useSidebar();
  const user = usePlatformAuthStore((state) => state.user);

  const visibleNavItems = useMemo(
    () =>
      navItems.filter(
        (item) => !item.permission || platformHasPermission(user, item.permission),
      ),
    [user],
  );

  const canViewPlaybooks = platformHasPermission(user, PLATFORM_PERMS.playbooksView);

  const closeMobile = () => {
    if (isMobile) {
      setOpenMobile(false);
    }
  };

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
      <SidebarHeader className="border-b border-sidebar-border p-4 group-data-[collapsible=icon]:p-2">
        <SidebarBrand variant="platform" />
      </SidebarHeader>
      <SidebarContent className="scrollbar-hide px-2 py-2">
        <div className="mt-2">
          <div className="px-3 py-2 text-xs font-medium text-sidebar-foreground/45 group-data-[collapsible=icon]:hidden">
            Central
          </div>
          <SidebarMenu>
            {visibleNavItems.map((item) => {
              const active = isNavActive(pathname, item.href, item.exact ?? false);
              return (
                <SidebarMenuItem key={item.href}>
                  <SidebarMenuButton
                    render={<Link href={item.href} prefetch={false} onClick={closeMobile} />}
                    tooltip={item.title}
                    isActive={active}
                    className={navButtonClass}
                  >
                    <item.icon className="h-4 w-4" />
                    <span>{item.title}</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              );
            })}
          </SidebarMenu>
        </div>
      </SidebarContent>
      <SidebarFooter className="border-t border-sidebar-border p-2">
        <SidebarMenu>
          {canViewPlaybooks ? (
            <SidebarMenuItem>
              <SidebarMenuButton
                render={<Link href="/platform/playbooks" prefetch={false} onClick={closeMobile} />}
                tooltip="Rollout playbooks (Project-One)"
                className={navButtonClass}
              >
                <BookOpen className="h-4 w-4" />
                <span className="group-data-[collapsible=icon]:hidden">Rollout playbooks</span>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ) : null}
          <SidebarMenuItem>
            <SidebarMenuButton
              render={<Link href="/login" prefetch={false} onClick={closeMobile} />}
              tooltip="Open tenant application"
              className={navButtonClass}
            >
              <LogIn className="h-4 w-4" />
              <span className="group-data-[collapsible=icon]:hidden">Tenant application</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
        <div className="mt-2 space-y-2 rounded-md border border-sidebar-border bg-sidebar-accent/50 p-3 group-data-[collapsible=icon]:hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-sidebar-foreground/45">Scope</span>
            <span className="text-xs font-medium text-sidebar-foreground">Central</span>
          </div>
          <p className="text-xs leading-snug text-sidebar-foreground/55">
            Operator tools for tenant lifecycle. Tenant workspaces use the main app after sign-in.
          </p>
        </div>
      </SidebarFooter>
    </Sidebar>
  );
}
