"use client";

import * as React from "react";
import { ContextMenu as ContextMenuPrimitive } from "@base-ui/react/context-menu";

import { cn } from "@/lib/utils";

function ContextMenu({ ...props }: ContextMenuPrimitive.Root.Props) {
  return <ContextMenuPrimitive.Root data-slot="context-menu" {...props} />;
}

function ContextMenuTrigger({ className, ...props }: ContextMenuPrimitive.Trigger.Props) {
  return (
    <ContextMenuPrimitive.Trigger
      data-slot="context-menu-trigger"
      className={cn("select-none", className)}
      {...props}
    />
  );
}

function ContextMenuContent({
  className,
  align = "start",
  side = "bottom",
  sideOffset = 4,
  children,
  ...props
}: ContextMenuPrimitive.Popup.Props &
  Pick<ContextMenuPrimitive.Positioner.Props, "align" | "side" | "sideOffset">) {
  return (
    <ContextMenuPrimitive.Portal>
      <ContextMenuPrimitive.Positioner
        align={align}
        side={side}
        sideOffset={sideOffset}
        className="isolate z-[100]"
      >
        <ContextMenuPrimitive.Popup
          data-slot="context-menu-content"
          className={cn(
            "z-[100] min-w-[10.5rem] origin-(--transform-origin) overflow-hidden rounded-lg border border-border bg-popover py-1 text-popover-foreground shadow-lg outline-none",
            "transition duration-150 ease-out data-ending-style:scale-95 data-ending-style:opacity-0 data-starting-style:scale-95 data-starting-style:opacity-0",
            className,
          )}
          {...props}
        >
          {children}
        </ContextMenuPrimitive.Popup>
      </ContextMenuPrimitive.Positioner>
    </ContextMenuPrimitive.Portal>
  );
}

function ContextMenuItem({
  className,
  destructive = false,
  inset = false,
  ...props
}: ContextMenuPrimitive.Item.Props & { destructive?: boolean; inset?: boolean }) {
  return (
    <ContextMenuPrimitive.Item
      data-slot="context-menu-item"
      className={cn(
        "flex cursor-default items-center gap-2 px-3 py-2 text-sm outline-none select-none",
        "data-highlighted:bg-muted data-disabled:pointer-events-none data-disabled:opacity-50",
        destructive
          ? "text-destructive data-highlighted:bg-destructive/10 data-highlighted:text-destructive"
          : "text-foreground",
        inset && "pl-8",
        className,
      )}
      {...props}
    />
  );
}

function ContextMenuSeparator({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <ContextMenuPrimitive.Separator
      data-slot="context-menu-separator"
      className={cn("my-1 h-px bg-border", className)}
      {...props}
    />
  );
}

function ContextMenuLabel({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="context-menu-label"
      className={cn("px-3 py-1.5 text-xs font-medium text-muted-foreground", className)}
      {...props}
    />
  );
}

function ContextMenuShortcut({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="context-menu-shortcut"
      className={cn("ml-auto text-[11px] tracking-wide text-muted-foreground", className)}
      {...props}
    />
  );
}

export {
  ContextMenu,
  ContextMenuTrigger,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuLabel,
  ContextMenuShortcut,
};
