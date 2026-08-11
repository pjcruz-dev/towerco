"use client";

import Link from "next/link";
import { MoreVertical } from "lucide-react";
import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLinkItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";

export type RowActionItem =
  | {
      type?: "item";
      key: string;
      label: ReactNode;
      onSelect?: () => void;
      href?: string;
      disabled?: boolean;
      destructive?: boolean;
      icon?: ReactNode;
      hidden?: boolean;
    }
  | {
      type: "separator";
      key: string;
      hidden?: boolean;
    };

type Props = {
  items: RowActionItem[];
  align?: "start" | "center" | "end";
  disabled?: boolean;
  label?: string;
  className?: string;
  /** Optional primary control shown before the kebab (e.g. Manage). */
  leading?: ReactNode;
};

export function RowActionsMenu({
  items,
  align = "end",
  disabled = false,
  label = "More actions",
  className,
  leading,
}: Props) {
  const visible = items.filter((item) => !item.hidden);
  if (visible.length === 0 && !leading) {
    return null;
  }

  return (
    <div
      className={cn("inline-flex items-center justify-end gap-1", className)}
      onClick={(event) => event.stopPropagation()}
    >
      {leading}
      {visible.length > 0 ? (
        <DropdownMenu>
          <DropdownMenuTrigger
            disabled={disabled}
            render={
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="size-8 rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label={label}
              >
                <MoreVertical className="size-4" />
                <span className="sr-only">{label}</span>
              </Button>
            }
          />
          <DropdownMenuContent align={align} className="min-w-[11rem]">
            {visible.map((item) => {
              if (item.type === "separator") {
                return <DropdownMenuSeparator key={item.key} />;
              }

              if (item.href) {
                const external = /^https?:\/\//i.test(item.href);
                if (external) {
                  return (
                    <DropdownMenuLinkItem
                      key={item.key}
                      href={item.href}
                      closeOnClick
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {item.icon}
                      {item.label}
                    </DropdownMenuLinkItem>
                  );
                }

                return (
                  <DropdownMenuLinkItem
                    key={item.key}
                    closeOnClick
                    render={<Link href={item.href} prefetch={false} />}
                  >
                    {item.icon}
                    {item.label}
                  </DropdownMenuLinkItem>
                );
              }

              return (
                <DropdownMenuItem
                  key={item.key}
                  disabled={item.disabled}
                  destructive={item.destructive}
                  onClick={() => item.onSelect?.()}
                >
                  {item.icon}
                  {item.label}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      ) : null}
    </div>
  );
}
