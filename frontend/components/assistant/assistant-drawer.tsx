"use client";

import { FileText, Minus, XIcon } from "lucide-react";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { AssistantChatPanel } from "@/components/assistant/assistant-chat-panel";
import { TowerOsAssistantMark } from "@/components/assistant/toweros-assistant-mark";
import { Button } from "@/components/ui/button";
import { useAssistantDrawer } from "@/hooks/use-assistant-drawer";
import { resolveAssistantRouteContext } from "@/lib/assistant/route-context";
import { cn } from "@/lib/utils";

const AUTO_PILOT_KEY = "toweros.assistant.auto_pilot";
const PLAN_MODE_KEY = "toweros.assistant.plan_mode";
const MODEL_KEY = "toweros.assistant.preferred_model";

export function AssistantDrawer() {
  const { open, setOpen, minimized, setMinimized } = useAssistantDrawer();
  const pathname = usePathname();
  const routeContext = resolveAssistantRouteContext(pathname);
  const [autoPilot, setAutoPilot] = useState(false);
  const [planMode, setPlanMode] = useState(false);
  const [preferredModel, setPreferredModel] = useState<string | null>(null);

  useEffect(() => {
    if (typeof window === "undefined") return;
    setAutoPilot(window.localStorage.getItem(AUTO_PILOT_KEY) === "1");
    setPlanMode(window.localStorage.getItem(PLAN_MODE_KEY) === "1");
    setPreferredModel(window.localStorage.getItem(MODEL_KEY));
  }, []);

  function toggleAutoPilot() {
    setAutoPilot((prev) => {
      const next = !prev;
      window.localStorage.setItem(AUTO_PILOT_KEY, next ? "1" : "0");
      return next;
    });
  }

  function togglePlanMode() {
    setPlanMode((prev) => {
      const next = !prev;
      window.localStorage.setItem(PLAN_MODE_KEY, next ? "1" : "0");
      return next;
    });
  }

  function onModelChange(model: string) {
    setPreferredModel(model);
    window.localStorage.setItem(MODEL_KEY, model);
  }

  // Keep the chat panel mounted when closed so conversation state survives
  // closing/reopening the widget within the same page session.
  return (
    <div
      className={cn(
        "fixed inset-0 z-50 flex items-end justify-end p-4 sm:p-6",
        open && !minimized ? "pointer-events-auto" : "pointer-events-none invisible",
      )}
      aria-hidden={!open || minimized}
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
          "pointer-events-auto relative flex h-[min(680px,calc(100vh-5.5rem))] w-full max-w-[420px] flex-col overflow-hidden",
          "rounded-2xl border border-slate-700/60 bg-card text-card-foreground shadow-2xl",
        )}
      >
        <div className="bg-slate-900 px-4 py-3 text-white">
          <div className="flex items-start justify-between gap-3">
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="flex size-9 items-center justify-center rounded-lg bg-sky-500/20 text-sky-300 ring-1 ring-sky-400/30">
                <TowerOsAssistantMark className="size-5" />
              </div>
              <div className="min-w-0">
                <h2 id="assistant-chat-title" className="truncate text-sm font-semibold tracking-tight">
                  AI Assistant
                </h2>
                <p className="truncate text-[11px] text-slate-300">Always Online</p>
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-0.5">
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="text-slate-300 hover:bg-white/10 hover:text-white"
                aria-label="Minimize assistant"
                onClick={() => setMinimized(true)}
              >
                <Minus className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="text-slate-300 hover:bg-white/10 hover:text-white"
                aria-label="Close assistant"
                onClick={() => setOpen(false)}
              >
                <XIcon className="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div className="mt-3 flex items-center justify-between gap-3 border-t border-white/10 pt-2.5">
            <button
              type="button"
              onClick={toggleAutoPilot}
              className="flex items-center gap-2 text-left text-xs text-slate-200"
              title="When On, proposed write actions are confirmed automatically (requires action permission)."
            >
              <span className="font-medium">Auto-Pilot</span>
              <span
                className={cn(
                  "relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors",
                  autoPilot ? "bg-emerald-500" : "bg-slate-600",
                )}
              >
                <span
                  className={cn(
                    "absolute top-0.5 size-4 rounded-full bg-white shadow transition-transform",
                    autoPilot ? "translate-x-4" : "translate-x-0.5",
                  )}
                />
              </span>
              <span className={cn("font-medium", autoPilot ? "text-emerald-300" : "text-red-300")}>
                {autoPilot ? "On" : "Off"}
              </span>
            </button>
            <Button
              type="button"
              size="sm"
              className={cn(
                "h-7 gap-1.5 rounded-md px-2.5 text-xs font-semibold",
                planMode
                  ? "bg-amber-400 text-slate-900 hover:bg-amber-300"
                  : "bg-amber-500/90 text-slate-900 hover:bg-amber-400",
              )}
              onClick={togglePlanMode}
              title="Ask for an implementation plan without proposing write actions."
            >
              <FileText className="size-3.5" />
              Plan{planMode ? " · On" : ""}
            </Button>
          </div>
        </div>

        <AssistantChatPanel
          routeContext={routeContext}
          open={open && !minimized}
          autoPilot={autoPilot}
          planMode={planMode}
          preferredModel={preferredModel}
          onPreferredModelChange={onModelChange}
        />
      </div>
    </div>
  );
}
