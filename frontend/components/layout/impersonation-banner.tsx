"use client";

import { LogOut, UserRound } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { endUserImpersonation } from "@/lib/auth/impersonation-session";
import { getErrorMessage } from "@/lib/api/error";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

export function ImpersonationBanner() {
  const user = useAuthStore((state) => state.user);
  const [ending, setEnding] = useState(false);
  const notify = useNotificationStore((state) => state.push);

  if (!user?.isImpersonating) {
    return null;
  }

  const isPlatformSource = user.impersonator?.source === "platform";
  const impersonatorLabel = user.impersonator
    ? `${user.impersonator.name} (${user.impersonator.email})`
    : "your administrator account";

  async function handleEnd() {
    setEnding(true);
    try {
      if (isPlatformSource) {
        useAuthStore.getState().clearSession();
        if (typeof window !== "undefined") {
          window.location.assign("/login");
        }
        return;
      }

      await endUserImpersonation();
    } catch (error) {
      notify({
        level: "error",
        title: "Could not end impersonation",
        message: getErrorMessage(error),
      });
      setEnding(false);
    }
  }

  return (
    <div
      role="status"
      className="flex flex-wrap items-center justify-between gap-3 border-b border-warning/40 bg-warning/10 px-4 py-2.5 text-sm text-foreground"
    >
      <div className="flex min-w-0 items-start gap-2">
        <UserRound className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden />
        <p className="min-w-0 leading-snug">
          <span className="font-medium">Viewing as {user.name}</span>
          <span className="text-muted-foreground"> ({user.email})</span>
          <span className="block text-xs text-muted-foreground sm:inline sm:before:content-['·_']">
            {isPlatformSource
              ? `Platform support session by ${impersonatorLabel}. Actions are audited.`
              : `Signed in as ${impersonatorLabel}. Actions are recorded under your admin session.`}
          </span>
        </p>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="shrink-0 border-warning/50 bg-background/80"
        disabled={ending}
        onClick={() => void handleEnd()}
      >
        <LogOut className="size-3.5" />
        {ending ? "Ending…" : "End session"}
      </Button>
    </div>
  );
}
