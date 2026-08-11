"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

type AssistantDrawerContextValue = {
  open: boolean;
  setOpen: (open: boolean) => void;
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
  const [open, setOpen] = useState(false);

  const toggle = useCallback(() => {
    setOpen((current) => !current);
  }, []);

  useEffect(() => {
    if (!enabled) {
      setOpen(false);
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
        setOpen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [enabled, toggle]);

  const value = useMemo(
    () => ({
      open,
      setOpen,
      toggle,
    }),
    [open, toggle],
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
