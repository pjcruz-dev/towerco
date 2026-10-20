"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";

export type AssistantLauncherActivity = "idle" | "working" | "done";

type AssistantDrawerContextValue = {
  open: boolean;
  minimized: boolean;
  activity: AssistantLauncherActivity;
  setOpen: (open: boolean) => void;
  setMinimized: (minimized: boolean) => void;
  setActivity: (activity: AssistantLauncherActivity) => void;
  toggle: () => void;
};

const AssistantDrawerContext = createContext<AssistantDrawerContextValue | null>(null);

export function AssistantDrawerProvider({
  children,
  enabled = true,
}: {
  children: React.ReactNode;
  enabled?: boolean;
}) {
  const [open, setOpenState] = useState(false);
  const [minimized, setMinimized] = useState(false);
  const [activity, setActivityState] = useState<AssistantLauncherActivity>("idle");
  const openRef = useRef(open);
  const minimizedRef = useRef(minimized);
  const doneTimer = useRef<number | null>(null);
  openRef.current = open;
  minimizedRef.current = minimized;

  const setOpen = useCallback((next: boolean) => {
    setOpenState(next);
    if (next) {
      setMinimized(false);
    }
  }, []);

  const setActivity = useCallback((next: AssistantLauncherActivity) => {
    if (doneTimer.current !== null) {
      window.clearTimeout(doneTimer.current);
      doneTimer.current = null;
    }
    setActivityState(next);
    if (next === "done") {
      doneTimer.current = window.setTimeout(() => {
        setActivityState("idle");
        doneTimer.current = null;
      }, 5000);
    }
  }, []);

  const toggle = useCallback(() => {
    if (openRef.current && minimizedRef.current) {
      setMinimized(false);
      return;
    }
    setOpenState((current) => !current);
    setMinimized(false);
  }, []);

  useEffect(() => {
    if (!enabled) {
      setOpenState(false);
      setMinimized(false);
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null;
      const tag = target?.tagName?.toLowerCase();
      const isEditable =
        tag === "input" ||
        tag === "textarea" ||
        tag === "select" ||
        Boolean(target?.isContentEditable);

      if ((event.metaKey || event.ctrlKey) && event.key === "/") {
        event.preventDefault();
        toggle();
        return;
      }

      if (event.key === "Escape" && !isEditable) {
        setOpenState(false);
        setMinimized(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [enabled, toggle]);

  const value = useMemo(
    () => ({
      open,
      minimized,
      activity,
      setOpen,
      setMinimized,
      setActivity,
      toggle,
    }),
    [open, minimized, activity, setOpen, setActivity, toggle],
  );

  return (
    <AssistantDrawerContext.Provider value={value}>{children}</AssistantDrawerContext.Provider>
  );
}

export function useAssistantDrawer(): AssistantDrawerContextValue {
  const context = useContext(AssistantDrawerContext);
  if (!context) {
    throw new Error("useAssistantDrawer must be used within AssistantDrawerProvider");
  }

  return context;
}
