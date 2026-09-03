"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import type { LucideIcon } from "lucide-react";

import { SidebarBrand } from "@/components/layout/sidebar-brand";
import { SidebarNavGroup, type SidebarSubNavItem } from "@/components/layout/sidebar-nav-group";
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { useWorkspaceNavGroups } from "@/hooks/use-workspace-nav-groups";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import { isTicketingTourActive } from "@/lib/help/ticketing-live-tour";
import { isNavActive } from "@/lib/navigation/is-nav-active";

type SubNav = SidebarSubNavItem & { permissions: string[]; section?: string };

function resolveNavGroupHomeHref(items: SubNav[]): string {
  const overview = items.find((item) => item.exact);
  return overview?.href ?? items[0]!.href;
}

const navButtonClass =
  "text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-active:bg-sidebar-accent data-active:text-sidebar-accent-foreground";

function SidebarNavLink({
  title,
  href,
  exact,
  icon: Icon,
  badge,
}: {
  title: string;
  href: string;
  exact?: boolean;
  icon: LucideIcon;
  badge?: number;
}) {
  const pathname = usePathname();
  const { isMobile, setOpenMobile } = useSidebar();
  const active = isNavActive(pathname, href, exact);

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        render={
          <Link
            href={href}
            prefetch={false}
            onClick={() => {
              if (isMobile) {
                setOpenMobile(false);
              }
            }}
          />
        }
        tooltip={title}
        isActive={active}
        className={navButtonClass}
      >
        <Icon className="size-4" />
        <span>{title}</span>
        {badge !== undefined && badge > 0 ? (
          <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-primary px-1.5 text-[10px] font-medium text-sidebar-primary-foreground group-data-[collapsible=icon]:hidden">
            {badge > 99 ? "99+" : badge}
          </span>
        ) : null}
      </SidebarMenuButton>
    </SidebarMenuItem>
  );
}

export function AppSidebar() {
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams) || isTicketingTourActive(searchParams);
  const { groups: groupsWithBadges, notificationUnread } = useWorkspaceNavGroups();

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-sidebar-border bg-sidebar text-sidebar-foreground print:hidden">
      <SidebarHeader className="border-b border-sidebar-border p-4 group-data-[collapsible=icon]:p-2">
        <SidebarBrand variant="tenant" />
      </SidebarHeader>
      <SidebarContent className="scrollbar-hide gap-1 px-2 py-2">
        {groupsWithBadges.map((group) => (
          <div key={group.group} className="mt-2 first:mt-0">
            <div className="px-2 py-2 text-xs font-medium text-sidebar-foreground/45 group-data-[collapsible=icon]:hidden">
              {group.group}
            </div>
            <SidebarMenu>
              {group.items.map((item) =>
                item.items ? (
                  <SidebarNavGroup
                    key={`${item.module ?? "nav"}:${item.title}`}
                    title={item.title}
                    icon={item.icon}
                    href={resolveNavGroupHomeHref(item.items as SubNav[])}
                    dataHelp={
                      item.title === "Settings"
                        ? "ea-nav-settings"
                        : item.title === "E-Approval"
                          ? "ea-nav-e-approval"
                          : item.title === "Ticketing"
                            ? "tk-nav-ticketing"
                            : undefined
                    }
                    forceOpen={
                      tourActive &&
                      (item.title === "Settings" ||
                        item.title === "E-Approval" ||
                        item.title === "Ticketing")
                    }
                    items={item.items.map(({ title, href, exact, section, badge }) => {
                      const pathOnly = href.split("?")[0] ?? href;
                      const eApprovalNav =
                        pathOnly === "/e-approval"
                          ? { dataHelp: "ea-nav-e-approval-overview", tourNav: "/e-approval" }
                          : pathOnly === "/e-approval/submissions"
                            ? {
                                dataHelp: "ea-nav-e-approval-submissions",
                                tourNav: "/e-approval/submissions",
                              }
                            : pathOnly === "/e-approval/approvals"
                              ? {
                                  dataHelp: "ea-nav-e-approval-approvals",
                                  tourNav: "/e-approval/approvals",
                                }
                              : pathOnly === "/e-approval/profile"
                                ? {
                                    dataHelp: "ea-nav-e-approval-profile",
                                    tourNav: "/e-approval/profile",
                                  }
                                : null;
                      const ticketingNav =
                        pathOnly === "/ticketing"
                          ? { dataHelp: "tk-nav-ticketing-overview", tourNav: "/ticketing" }
                          : pathOnly === "/ticketing/tickets"
                            ? {
                                dataHelp: "tk-nav-ticketing-tickets",
                                tourNav: "/ticketing/tickets",
                              }
                            : pathOnly === "/ticketing/tickets/new"
                              ? {
                                  dataHelp: "tk-nav-ticketing-new",
                                  tourNav: "/ticketing/tickets/new",
                                }
                              : pathOnly === "/ticketing/settings"
                                ? {
                                    dataHelp: "tk-nav-ticketing-settings",
                                    tourNav: "/ticketing/settings",
                                  }
                                : null;
                      const tourNavMeta = eApprovalNav ?? ticketingNav;
                      return {
                        title,
                        href,
                        exact,
                        section,
                        badge,
                        dataHelp: tourNavMeta?.dataHelp,
                        tourNav: tourNavMeta?.tourNav,
                      };
                    })}
                    buttonClassName={navButtonClass}
                  />
                ) : item.href ? (
                  <SidebarNavLink
                    key={`${item.module ?? "nav"}:${item.title}:${item.href}`}
                    title={item.title}
                    href={item.href}
                    exact={item.exact}
                    icon={item.icon}
                    badge={item.title === "Notifications" ? notificationUnread : undefined}
                  />
                ) : null,
              )}
            </SidebarMenu>
          </div>
        ))}
      </SidebarContent>
    </Sidebar>
  );
}
