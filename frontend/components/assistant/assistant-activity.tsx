"use client";

import { useEffect, useState } from "react";
import { Check, ChevronDown, CircleAlert, Lightbulb, Loader2 } from "lucide-react";

import type { AssistantActivityStep } from "@/lib/api/modules/assistant-api";
import { formatWorked, presentWorkStep, stepCountLabel, visibleWorkSteps } from "@/lib/assistant/work-log";
import { cn } from "@/lib/utils";

type Props = {
  steps: AssistantActivityStep[];
  thought?: string;
  live?: boolean;
  workedMs?: number | null;
  intent?: string | null;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
};

export function AssistantActivity({
  steps,
  thought = "",
  live = false,
  workedMs = null,
  intent = null,
  open,
  onOpenChange,
}: Props) {
  const [uncontrolledOpen, setUncontrolledOpen] = useState(true);
  const [elapsedMs, setElapsedMs] = useState(0);
  const expanded = open ?? uncontrolledOpen;

  useEffect(() => {
    if (!live) {
      return;
    }
    const started = Date.now();
    const timer = window.setInterval(() => {
      setElapsedMs(Date.now() - started);
    }, 500);
    return () => window.clearInterval(timer);
  }, [live]);

  const visible = visibleWorkSteps(steps, thought, live)
    .map((step) => presentWorkStep(step, intent))
    .filter((step) => step.kind !== "thought" || step.result !== "");
  if (visible.length === 0) {
    return null;
  }

  const failed = visible.some((step) => step.status === "failed");
  const running = live || visible.some((step) => step.status === "running");
  const duration = live
    ? elapsedMs > 0
      ? formatWorked(elapsedMs)
      : null
    : workedMs != null && workedMs >= 0
      ? formatWorked(workedMs)
      : null;
  const heading = running
    ? `Working${duration ? ` · ${duration}` : ""}`
    : duration
      ? `Worked for ${duration}`
      : "Worked";

  function setExpanded(next: boolean) {
    onOpenChange?.(next);
    if (open === undefined) {
      setUncontrolledOpen(next);
    }
  }

  return (
    <div className="w-full">
      <button
        type="button"
        className="inline-flex max-w-full items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-left text-xs text-muted-foreground shadow-sm hover:bg-muted/70"
        aria-expanded={expanded}
        onClick={() => setExpanded(!expanded)}
      >
        {running ? (
          <Loader2 className="size-3.5 shrink-0 animate-spin" />
        ) : failed ? (
          <CircleAlert className="size-3.5 shrink-0 text-destructive" />
        ) : (
          <Check className="size-3.5 shrink-0 text-success" />
        )}
        <span className="font-medium text-foreground">{heading}</span>
        <span>{stepCountLabel(visible.length)}</span>
        <ChevronDown className={cn("size-3.5 shrink-0 transition-transform", expanded && "rotate-180")} />
      </button>
      {expanded ? (
        <ol className="mt-2 rounded-xl border border-border bg-card px-3 py-3 shadow-sm">
          {visible.map((step, index) => {
            const stepRunning = step.status === "running";
            const stepFailed = step.status === "failed";
            return (
              <li key={step.id} className="relative ps-6 pb-4 last:pb-0">
                {index < visible.length - 1 ? (
                  <span className="absolute start-[7px] top-4 bottom-0 w-px bg-border" aria-hidden />
                ) : null}
                <span className="absolute start-0 top-0.5 flex size-4 items-center justify-center bg-card text-success">
                  {step.kind === "thought" ? (
                    <Lightbulb className="size-3.5 text-muted-foreground" />
                  ) : stepRunning ? (
                    <Loader2 className="size-3.5 animate-spin text-muted-foreground" />
                  ) : stepFailed ? (
                    <CircleAlert className="size-3.5 text-destructive" />
                  ) : (
                    <Check className="size-3.5" />
                  )}
                </span>
                {step.kind === "thought" ? (
                  <p className="whitespace-pre-wrap text-xs leading-relaxed text-foreground">{step.result}</p>
                ) : (
                  <div className="min-w-0">
                    <p className="text-xs leading-snug text-foreground">{step.title}</p>
                    {step.result !== "" ? (
                      <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{step.result}</p>
                    ) : null}
                  </div>
                )}
              </li>
            );
          })}
        </ol>
      ) : null}
    </div>
  );
}
