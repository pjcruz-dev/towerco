"use client";

import { XIcon } from "lucide-react";
import { usePathname } from "next/navigation";

import { AssistantChatPanel } from "@/components/assistant/assistant-chat-panel";
import { Button } from "@/components/ui/button";
import { useAssistantDrawer } from "@/hooks/use-assistant-drawer";
import { resolveAssistantRouteContext } from "@/lib/assistant/route-context";
import { cn } from "@/lib/utils";

export function AssistantDrawer() {
  const { open, setOpen } = useAssistantDrawer();
  const pathname = usePathname();
  const routeContext = resolveAssistantRouteContext(pathname);

  // Keep the chat panel mounted when closed so conversation state survives
  // closing/reopening the widget within the same page session.
  return (
    <div
      className={cn(
        "fixed inset-0 z-50 flex items-end justify-end p-4 sm:p-6",
        open ? "pointer-events-auto" : "pointer-events-none invisible",
      )}
      aria-hidden={!open}
    >
      <button
        type="button"
        aria-label="Close assistant backdrop"
        className="pointer-events-auto absolute inset-0 bg-slate-900/10 transition-opacity"
        onClick={() => setOpen(false)}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="assistant-chat-title"
        className={cn(
          "pointer-events-auto relative flex h-[min(640px,calc(100vh-5.5rem))] w-full max-w-[400px] flex-col overflow-hidden",
          "rounded-2xl border border-border bg-card text-card-foreground shadow-xl",
        )}
      >
        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
          <div className="min-w-0">
            <h2 id="assistant-chat-title" className="truncate text-sm font-semibold text-foreground">
              Ask TowerOS
            </h2>
            <p className="truncate text-xs text-muted-foreground">
              How-to and process guidance for your workspace
            </p>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label="Close assistant"
            onClick={() => setOpen(false)}
          >
            <XIcon className="h-4 w-4" />
          </Button>
        </div>
        <AssistantChatPanel routeContext={routeContext} open={open} />
      </div>
    </div>
  );
}
