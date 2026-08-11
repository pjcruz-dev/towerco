"use client";

import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type Props = {
  submitLabel: string;
  isSubmitting?: boolean;
  onSaveDraft?: () => void;
  showSaveDraft?: boolean;
  disabled?: boolean;
  /** When the footer is rendered outside the form (e.g. sheet), wire submit to this form id. */
  formId?: string;
};

export function RolloutFieldFormFooter({
  submitLabel,
  isSubmitting,
  onSaveDraft,
  showSaveDraft = false,
  disabled,
  formId,
}: Props) {
  const [online, setOnline] = useState(true);

  useEffect(() => {
    const sync = () => setOnline(typeof navigator !== "undefined" ? navigator.onLine : true);
    sync();
    window.addEventListener("online", sync);
    window.addEventListener("offline", sync);
    return () => {
      window.removeEventListener("online", sync);
      window.removeEventListener("offline", sync);
    };
  }, []);

  return (
    <div className="sticky bottom-0 z-10 -mx-4 mt-4 border-t border-border bg-card/95 px-4 py-3 backdrop-blur-sm supports-[padding:max(0px)]:pb-[max(0.75rem,env(safe-area-inset-bottom))] md:static md:mx-0 md:mt-0 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none">
      <div className="mb-2 flex items-center justify-between gap-2 sm:mb-0 sm:hidden">
        <span
          className={cn(
            "inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-medium",
            online
              ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
              : "bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100",
          )}
        >
          <span
            className={cn("h-1.5 w-1.5 rounded-full", online ? "bg-emerald-600" : "bg-amber-600")}
            aria-hidden
          />
          {online ? "Online" : "Offline — drafts save locally"}
        </span>
      </div>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p className="hidden text-[11px] text-muted-foreground sm:block">
          <span
            className={cn(
              "mr-2 inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 font-medium",
              online
                ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                : "bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100",
            )}
          >
            <span
              className={cn("h-1.5 w-1.5 rounded-full", online ? "bg-emerald-600" : "bg-amber-600")}
              aria-hidden
            />
            {online ? "Online" : "Offline"}
          </span>
          {showSaveDraft
            ? "Save draft offline if the network drops; use Sync drafts on the rollout header when back online. Photos need connectivity to upload."
            : null}
        </p>
        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
          {showSaveDraft && onSaveDraft ? (
            <Button
              type="button"
              variant="outline"
              size="lg"
              className="min-h-11 w-full sm:w-auto"
              disabled={disabled || isSubmitting}
              onClick={onSaveDraft}
            >
              Save draft offline
            </Button>
          ) : null}
          <Button
            type="submit"
            form={formId}
            size="lg"
            className="min-h-11 w-full sm:w-auto"
            disabled={disabled || isSubmitting || !online}
            title={!online ? "Reconnect to submit to the server" : undefined}
          >
            {isSubmitting ? "Saving…" : submitLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
