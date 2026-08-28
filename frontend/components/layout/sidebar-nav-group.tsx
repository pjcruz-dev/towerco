"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronRight, type LucideIcon } from "lucide-react";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import {
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { isNavActive } from "@/lib/navigation/is-nav-active";
import { cn } from "@/lib/utils";

export type SidebarSubNavItem = {
  title: string;
  href: string;
  /** When true, only highlight on exact path match (module dashboards). */
  exact?: boolean;
  /** Optional section label rendered above the first item in a group (e.g. Operate, Decide). */
  section?: string;
  /** Optional unread/action count rendered beside the label. */
  badge?: number;
  /** Live product tour hook (`[data-help="…"]`). */
  dataHelp?: string;
  /** Path used by tour auto-nav when advancing from this item. */
  tourNav?: string;
};

type SidebarNavGroupProps = {
  title: string;
  icon: LucideIcon;
  /** Module home — label/icon navigate here; chevron toggles children only. */
  href: string;
  items: SidebarSubNavItem[];
  buttonClassName?: string;
  subButtonClassName?: string;
  /** Live product tour hook on the group row (e.g. Settings). */
  dataHelp?: string;
  /** When true, keep the group expanded (e.g. during a live tour). */
  forceOpen?: boolean;
};

const groupShellClass =
  "text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-active:bg-sidebar-accent data-active:text-sidebar-accent-foreground";

const NAV_SECTION_ORDER = ["Operate", "Decide", "Finance", "Configure"] as const;

function sortNavItemsBySection(items: SidebarSubNavItem[]): SidebarSubNavItem[] {
  const rank = (section?: string) => {
    const index = NAV_SECTION_ORDER.indexOf(section as (typeof NAV_SECTION_ORDER)[number]);
    return index === -1 ? NAV_SECTION_ORDER.length : index;
  };

  return [...items].sort((left, right) => rank(left.section) - rank(right.section));
}

export function SidebarNavGroup({
  title,
  icon: Icon,
  href,
  items,
  buttonClassName,
  subButtonClassName,
  dataHelp,
  forceOpen = false,
}: SidebarNavGroupProps) {
  const pathname = usePathname();
  const { state, isMobile, setOpenMobile } = useSidebar();
  const containerRef = useRef<HTMLLIElement>(null);
  const triggerRef = useRef<HTMLDivElement>(null);
  const flyoutRef = useRef<HTMLDivElement>(null);
  const [flyoutPosition, setFlyoutPosition] = useState({ top: 0, left: 0 });
  const collapsed = state === "collapsed" && !isMobile;

  const sortedItems = sortNavItemsBySection(items);

  const homeActive = isNavActive(pathname, href, true);
  const groupActive = homeActive || sortedItems.some((item) => isNavActive(pathname, item.href, item.exact));
  const [open, setOpen] = useState(groupActive || forceOpen);
  const [flyoutOpen, setFlyoutOpen] = useState(false);

  useEffect(() => {
    if (groupActive || forceOpen) {
      setOpen(true);
    }
  }, [forceOpen, groupActive, pathname]);

  useLayoutEffect(() => {
    if (!flyoutOpen || !triggerRef.current) {
      return;
    }

    const updatePosition = () => {
      const rect = triggerRef.current?.getBoundingClientRect();
      if (!rect) {
        return;
      }

      setFlyoutPosition({
        top: rect.top,
        left: rect.right + 8,
      });
    };

    updatePosition();
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);

    return () => {
      window.removeEventListener("resize", updatePosition);
      window.removeEventListener("scroll", updatePosition, true);
    };
  }, [flyoutOpen]);

  useEffect(() => {
    if (!flyoutOpen) {
      return undefined;
    }

    const onPointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (containerRef.current?.contains(target) || flyoutRef.current?.contains(target)) {
        return;
      }
      setFlyoutOpen(false);
    };

    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [flyoutOpen]);

  const closeMobile = () => {
    if (isMobile) {
      setOpenMobile(false);
    }
  };

  const toggleOpen = () => setOpen((value) => !value);

  const collapsedFlyout =
    collapsed && flyoutOpen && typeof document !== "undefined"
      ? createPortal(
          <div
            ref={flyoutRef}
            style={{ top: flyoutPosition.top, left: flyoutPosition.left }}
            className="fixed z-[100] min-w-[12rem] overflow-hidden rounded-md border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-md"
          >
            <Link
              href={href}
              prefetch={false}
              onClick={() => {
                setFlyoutOpen(false);
                closeMobile();
              }}
              className="block border-b border-sidebar-border px-3 py-2 text-xs font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent"
            >
              {title}
            </Link>
            <ul className="py-1">
              {sortedItems.map((item, index) => {
                const active = isNavActive(pathname, item.href, item.exact);
                const showSection =
                  item.section &&
                  (index === 0 || sortedItems[index - 1]?.section !== item.section);

                return (
                  <li key={item.href}>
                    {showSection ? (
                      <p className="px-3 pb-1 pt-2 text-xs font-medium text-sidebar-foreground/45">
                        {item.section}
                      </p>
                    ) : null}
                    <Link
                      href={item.href}
                      prefetch={false}
                      onClick={() => {
                        setFlyoutOpen(false);
                        closeMobile();
                      }}
                      className={cn(
                        "flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                        active
                          ? "bg-sidebar-accent font-medium text-sidebar-accent-foreground"
                          : "text-sidebar-foreground/70",
                      )}
                    >
                      <span className="truncate">{item.title}</span>
                      {item.badge && item.badge > 0 ? (
                        <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-primary px-1.5 text-[10px] font-medium text-sidebar-primary-foreground">
                          {item.badge > 99 ? "99+" : item.badge}
                        </span>
                      ) : null}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>,
          document.body,
        )
      : null;

  if (collapsed) {
    return (
      <SidebarMenuItem ref={containerRef} className="relative">
        <div ref={triggerRef}>
          <SidebarMenuButton
            tooltip={flyoutOpen ? undefined : title}
            isActive={groupActive}
            onClick={() => setFlyoutOpen((value) => !value)}
            className={cn(groupShellClass, buttonClassName)}
          >
            <Icon className="h-4 w-4" />
          </SidebarMenuButton>
        </div>
        {collapsedFlyout}
      </SidebarMenuItem>
    );
  }

  return (
    <SidebarMenuItem>
      <div
        data-help={dataHelp}
        className={cn(
          "flex w-full items-center overflow-hidden rounded-md",
          groupActive && "bg-sidebar-accent text-sidebar-accent-foreground",
          !groupActive && "text-sidebar-foreground/70",
        )}
      >
        <SidebarMenuButton
          render={
            <Link
              href={href}
              prefetch={false}
              onClick={closeMobile}
              className="min-w-0 flex-1"
            />
          }
          isActive={groupActive}
          className={cn(
            "min-w-0 flex-1 rounded-none rounded-l-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
            buttonClassName,
          )}
        >
          <Icon className="h-4 w-4 shrink-0" />
          <span className="truncate">{title}</span>
        </SidebarMenuButton>
        <button
          type="button"
          aria-label={`${open ? "Collapse" : "Expand"} ${title} menu`}
          aria-expanded={open}
          onClick={toggleOpen}
          className={cn(
            "inline-flex h-9 w-8 shrink-0 items-center justify-center rounded-r-md text-sidebar-foreground/50 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
            groupActive && "text-sidebar-accent-foreground",
          )}
        >
          <ChevronRight
            className={cn("h-3.5 w-3.5 opacity-70 transition-transform", open && "rotate-90")}
            aria-hidden
          />
        </button>
      </div>
      {open ? (
        <SidebarMenuSub className="ml-3.5 border-l border-sidebar-border pl-2">
          {sortedItems.map((item, index) => {
            const active = isNavActive(pathname, item.href, item.exact);
            const showSection =
              item.section &&
              (index === 0 || sortedItems[index - 1]?.section !== item.section);

            return (
              <SidebarMenuSubItem key={item.href}>
                {showSection ? (
                  <p className="px-2 pb-1 pt-2 text-xs font-medium text-sidebar-foreground/45">
                    {item.section}
                  </p>
                ) : null}
                <SidebarMenuSubButton
                  render={
                    <Link
                      href={item.href}
                      prefetch={false}
                      onClick={closeMobile}
                      data-help={item.dataHelp}
                      data-tour-nav={item.tourNav ?? (item.dataHelp ? item.href : undefined)}
                    />
                  }
                  isActive={active}
                  className={cn(
                    "h-8 text-sidebar-foreground/55 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-active:bg-sidebar-accent data-active:font-medium data-active:text-sidebar-accent-foreground",
                    subButtonClassName,
                  )}
                >
                  <span className="text-xs">{item.title}</span>
                  {item.badge && item.badge > 0 ? (
                    <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-primary px-1.5 text-[10px] font-medium text-sidebar-primary-foreground">
                      {item.badge > 99 ? "99+" : item.badge}
                    </span>
                  ) : null}
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            );
          })}
        </SidebarMenuSub>
      ) : null}
    </SidebarMenuItem>
  );
}
