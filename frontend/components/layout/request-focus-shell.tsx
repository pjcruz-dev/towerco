"use client";

import Link from "next/link";
import { X } from "lucide-react";

import { ImpersonationBanner } from "@/components/layout/impersonation-banner";
import { buttonVariants } from "@/components/ui/button";
import { E_APPROVAL_FOCUS_MAX_WIDTH_CLASS } from "@/modules/e-approval/form-layout";
import { cn } from "@/lib/utils";

type Props = {
  children: React.ReactNode;
  title?: string;
  backHref?: string;
  backLabel?: string;
};

/** Minimal chrome for requestor form fill — no sidebar, no module nav. */
export function RequestFocusShell({
  children,
  title = "New request",
  backHref = "/e-approval/submissions/new",
  backLabel = "All forms",
}: Props) {
  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      <header className="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div
          className={cn(
            "mx-auto flex w-full items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8",
            E_APPROVAL_FOCUS_MAX_WIDTH_CLASS,
          )}
        >
          <div className="min-w-0">
            <p className="text-xs font-medium text-muted-foreground">E-Approval</p>
            <h1 className="truncate text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
          </div>
          <div className="flex shrink-0 items-center gap-1">
            <Link href={backHref} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              {backLabel}
            </Link>
            <Link
              href={backHref}
              aria-label="Close"
              className={cn(buttonVariants({ variant: "ghost", size: "icon-sm" }), "h-8 w-8")}
            >
              <X className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </header>
      <ImpersonationBanner />
      <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <div className={cn("mx-auto w-full", E_APPROVAL_FOCUS_MAX_WIDTH_CLASS)}>{children}</div>
      </main>
    </div>
  );
}
