"use client";

import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import { useSubscriptionAccessStore } from "@/stores/subscription-access-store";

export function SubscriptionAccessBanner() {
  const mode = useSubscriptionAccessStore((s) => s.mode);
  const message = useSubscriptionAccessStore((s) => s.message);

  if (!mode || mode === "full") {
    return null;
  }

  const isBlocked = mode === "blocked";

  return (
    <div
      role="status"
      className={
        isBlocked
          ? "border-b border-destructive/40 bg-destructive/10 px-4 py-2.5 text-sm text-foreground"
          : "border-b border-warning/40 bg-warning/10 px-4 py-2.5 text-sm text-foreground"
      }
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="min-w-0 leading-snug">
          <span className="font-medium">
            {isBlocked ? "Workspace access suspended" : "Read-only mode"}
          </span>
          {message ? (
            <span className="mt-0.5 block text-xs text-muted-foreground sm:mt-0 sm:inline sm:before:content-['·_']">
              {message}
            </span>
          ) : null}
        </p>
        <Link href="/billing" className={buttonVariants({ variant: "outline", size: "sm" })}>
          View billing
        </Link>
      </div>
    </div>
  );
}
