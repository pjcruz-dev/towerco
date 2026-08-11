"use client";

import { GitBranch, GitFork, Layers, Plus, Users } from "lucide-react";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type WorkflowAddKind = "step" | "if_else" | "ladder" | "parallel";

const OPTIONS: Array<{
  kind: WorkflowAddKind;
  label: string;
  description: string;
  icon: ReactNode;
}> = [
  {
    kind: "step",
    label: "Approval step",
    description: "One approver in sequence",
    icon: <Plus className="h-3.5 w-3.5" />,
  },
  {
    kind: "if_else",
    label: "If / Else",
    description: "Two exclusive bands on one threshold",
    icon: <GitBranch className="h-3.5 w-3.5" />,
  },
  {
    kind: "ladder",
    label: "Threshold ladder",
    description: "3+ exclusive amount / field bands",
    icon: <Layers className="h-3.5 w-3.5" />,
  },
  {
    kind: "parallel",
    label: "Parallel group",
    description: "Same step order — all / any / N of M",
    icon: <Users className="h-3.5 w-3.5" />,
  },
];

type Props = {
  onSelect: (kind: WorkflowAddKind) => void;
  label?: string;
  /** Compact + icon only (connector / band side). */
  iconOnly?: boolean;
  variant?: "outline" | "secondary" | "ghost" | "default";
  size?: "sm" | "icon-sm" | "default";
  align?: "start" | "end" | "center";
  /** Prefer top for connector + so the panel does not sit under the trigger. */
  side?: "top" | "bottom";
  className?: string;
  /** Applied only to the trigger control — never to menu items. */
  triggerClassName?: string;
  ariaLabel?: string;
};

export function EApprovalWorkflowAddMenu({
  onSelect,
  label = "Add",
  iconOnly = false,
  variant = "outline",
  size = "sm",
  align = "end",
  side = "bottom",
  className,
  triggerClassName,
  ariaLabel,
}: Props) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) {
      return;
    }
    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  return (
    <div ref={rootRef} className={cn("relative inline-flex", className)}>
      <Button
        type="button"
        size={size}
        variant={variant}
        className={cn("relative z-0", triggerClassName)}
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={ariaLabel ?? (iconOnly ? "Add to workflow" : label)}
        onClick={() => setOpen((prev) => !prev)}
      >
        {iconOnly ? (
          <Plus className="h-4 w-4" />
        ) : (
          <>
            <Plus className="mr-1 h-3.5 w-3.5" />
            {label}
            <GitFork className="ml-0.5 h-3 w-3 opacity-60" />
          </>
        )}
      </Button>
      {open ? (
        <div
          id={menuId}
          role="menu"
          className={cn(
            "absolute z-50 w-64 min-w-64 rounded-lg border border-border bg-card p-1 shadow-lg",
            side === "bottom" ? "top-full mt-2" : "bottom-full mb-2",
            align === "end" && "right-0",
            align === "start" && "left-0",
            align === "center" && "left-1/2 -translate-x-1/2",
          )}
        >
          <p className="px-2 py-1.5 text-[11px] font-medium text-muted-foreground">Insert into path</p>
          {OPTIONS.map((option) => (
            <button
              key={option.kind}
              type="button"
              role="menuitem"
              className="flex w-full min-w-0 items-start gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-muted/70"
              onClick={() => {
                setOpen(false);
                onSelect(option.kind);
              }}
            >
              <span className="mt-0.5 shrink-0 text-muted-foreground">{option.icon}</span>
              <span className="min-w-0 flex-1">
                <span className="block font-medium leading-snug text-foreground">{option.label}</span>
                <span className="mt-0.5 block text-[11px] leading-snug text-muted-foreground">
                  {option.description}
                </span>
              </span>
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
