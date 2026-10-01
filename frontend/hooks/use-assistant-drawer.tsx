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

type AssistantDrawerContextValue = {
  open: boolean;
  minimized: boolean;
  setOpen: (open: boolean) => void;
  setMinimized: (minimized: boolean) => void;
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
  const openRef = useRef(open);
  const minimizedRef = useRef(minimized);
  openRef.current = open;
  minimizedRef.current = minimized;

  const setOpen = useCallback((next: boolean) => {
    setOpenState(next);
    if (next) {
      setMinimized(false);
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
      setOpen,
      setMinimized,
      toggle,
    }),
    [open, minimized, setOpen, toggle],
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
