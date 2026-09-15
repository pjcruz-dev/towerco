"use client";

import { FileText, History, MessageSquarePlus, Minus, XIcon } from "lucide-react";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { AssistantChatPanel } from "@/components/assistant/assistant-chat-panel";
import { AssistantHeaderStatus } from "@/components/assistant/assistant-header-status";
import { TowerOsAssistantMark } from "@/components/assistant/toweros-assistant-mark";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { useAssistantDrawer } from "@/hooks/use-assistant-drawer";
import { resolveAssistantRouteContext } from "@/lib/assistant/route-context";
import {
  fetchAssistantMeta,
  type AssistantCostEstimate,
  type AssistantMeta,
  type AssistantRateLimit,
} from "@/lib/api/modules/assistant-api";
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
  const [historyOpen, setHistoryOpen] = useState(false);
  const [newChatNonce, setNewChatNonce] = useState(0);
  const [meta, setMeta] = useState<AssistantMeta | null>(null);
  const [rateLimit, setRateLimit] = useState<AssistantRateLimit | null>(null);
  const [costEstimate, setCostEstimate] = useState<AssistantCostEstimate | null>(null);

  useEffect(() => {
    if (typeof window === "undefined") return;
    setAutoPilot(window.localStorage.getItem(AUTO_PILOT_KEY) === "1");
    setPlanMode(window.localStorage.getItem(PLAN_MODE_KEY) === "1");
    setPreferredModel(window.localStorage.getItem(MODEL_KEY));
  }, []);

  useEffect(() => {
    if (!open || minimized) {
      setHistoryOpen(false);
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        const data = await fetchAssistantMeta();
        if (cancelled) return;
        setMeta(data);
        if (data.rate_limit) {
          setRateLimit(data.rate_limit);
        }
        const models = data.models ?? [];
        const stored = window.localStorage.getItem(MODEL_KEY);
        if (stored && models.includes(stored)) {
          setPreferredModel(stored);
        } else if (data.model_name) {
          setPreferredModel(data.model_name);
          window.localStorage.setItem(MODEL_KEY, data.model_name);
        } else if (stored) {
          // Stale Cursor model ids (e.g. auto-smart, composer-2) after provider switch.
          window.localStorage.removeItem(MODEL_KEY);
          setPreferredModel(null);
        }
      } catch {
        // Meta is optional chrome; chat still works.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [open, minimized]);

  useEffect(() => {
    if (!rateLimit || rateLimit.resets_in_seconds <= 0) {
      return;
    }
    const timer = window.setInterval(() => {
      setRateLimit((current) => {
        if (!current || current.resets_in_seconds <= 0) {
          return current;
        }
        const next = current.resets_in_seconds - 1;
        if (next <= 0) {
          return { ...current, remaining: current.limit, resets_in_seconds: 0 };
        }
        return { ...current, resets_in_seconds: next };
      });
    }, 1000);
    return () => window.clearInterval(timer);
  }, [rateLimit?.resets_in_seconds && rateLimit.resets_in_seconds > 0 ? "active" : "idle"]);

  function toggleAutoPilot(next?: boolean) {
    setAutoPilot((prev) => {
      const value = typeof next === "boolean" ? next : !prev;
      window.localStorage.setItem(AUTO_PILOT_KEY, value ? "1" : "0");
      return value;
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

  return (
    <div
      className={cn(
        "fixed inset-0 z-50 flex items-end justify-end p-4 sm:p-6",
        open && !minimized ? "pointer-events-auto" : "pointer-events-none invisible",
      )}
      aria-hidden={!open || minimized}
    >
      <Button
        type="button"
        variant="ghost"
        aria-label="Close assistant backdrop"
        className="pointer-events-auto absolute inset-0 h-auto w-auto rounded-none bg-black/40 p-0 hover:bg-black/40"
        onClick={() => setOpen(false)}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="assistant-chat-title"
        className="pointer-events-auto relative flex h-[min(680px,calc(100vh-5.5rem))] w-full max-w-[420px] flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-lg"
      >
        <div className="border-b border-border px-4 py-3">
          <div className="flex items-start justify-between gap-3">
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="flex size-9 items-center justify-center rounded-lg border border-border bg-muted text-foreground">
                <TowerOsAssistantMark className="size-5" />
              </div>
              <div className="min-w-0">
                <h2 id="assistant-chat-title" className="truncate text-sm font-semibold tracking-tight text-foreground">
                  AI Assistant
                </h2>
                <p className="truncate text-[11px] text-muted-foreground">Always online</p>
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-0.5">
              <Button
                type="button"
                variant={historyOpen ? "secondary" : "ghost"}
                size="icon-sm"
                aria-label={historyOpen ? "Close conversation history" : "Open conversation history"}
                title="Conversation history"
                onClick={() => setHistoryOpen((v) => !v)}
              >
                <History className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label="Start new chat"
                title="New chat"
                onClick={() => {
                  setHistoryOpen(false);
                  setCostEstimate(null);
                  setNewChatNonce((n) => n + 1);
                }}
              >
                <MessageSquarePlus className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label="Minimize assistant"
                onClick={() => setMinimized(true)}
              >
                <Minus className="h-4 w-4" />
              </Button>
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
          </div>

          <AssistantHeaderStatus
            meta={meta}
            rateLimit={rateLimit}
            costEstimate={costEstimate}
            selectedModel={preferredModel}
            planMode={planMode}
            onModelChange={onModelChange}
          />

          <div className="mt-2.5 flex items-center justify-between gap-3 border-t border-border pt-2.5">
            <label
              className="flex items-center gap-2 text-xs font-medium text-foreground"
              title="When On, proposed write actions are confirmed automatically (requires action permission)."
            >
              Auto-Pilot
              <Switch
                checked={autoPilot}
                onCheckedChange={(checked) => toggleAutoPilot(checked === true)}
                aria-label="Auto-Pilot"
              />
              <span className="font-normal text-muted-foreground">{autoPilot ? "On" : "Off"}</span>
            </label>
            <Button
              type="button"
              size="sm"
              variant={planMode ? "secondary" : "outline"}
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
          meta={meta}
          historyOpen={historyOpen}
          onHistoryOpenChange={setHistoryOpen}
          newChatNonce={newChatNonce}
          onRateLimitChange={setRateLimit}
          onCostEstimateChange={setCostEstimate}
        />
      </div>
    </div>
  );
}
