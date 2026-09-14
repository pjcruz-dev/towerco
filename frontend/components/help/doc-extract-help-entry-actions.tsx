"use client";

import Link from "next/link";
import { BookOpen, Play } from "lucide-react";

import { buttonVariants } from "@/components/ui/button";
import { dismissDocExtractTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import { docExtractTourStartHref } from "@/lib/help/doc-extract-live-tour";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

type DocExtractHelpEntryActionsProps = {
  className?: string;
  size?: "sm" | "default";
  showHelp?: boolean;
  showTour?: boolean;
};

/** Help hub + live tour — entry actions for DocExtract page headers. */
export function DocExtractHelpEntryActions({
  className,
  size = "sm",
  showHelp = true,
  showTour = true,
}: DocExtractHelpEntryActionsProps) {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);

  if (!showHelp && !showTour) return null;

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      {showHelp ? (
        <Link href="/help" className={cn(buttonVariants({ variant: "outline", size }))}>
          <BookOpen className="mr-1.5 h-3.5 w-3.5" aria-hidden />
          Help
        </Link>
      ) : null}
      {showTour ? (
        <Link
          href={docExtractTourStartHref(0)}
          className={cn(buttonVariants({ variant: "outline", size }))}
          onClick={() => dismissDocExtractTourPrompt(userId, tenantId)}
        >
          <Play className="mr-1.5 h-3.5 w-3.5" aria-hidden />
          Start tour
        </Link>
      ) : null}
    </div>
  );
}
