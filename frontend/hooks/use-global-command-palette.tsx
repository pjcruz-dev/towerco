"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

type GlobalCommandPaletteContextValue = {
  open: boolean;
  setOpen: (open: boolean) => void;
  toggle: () => void;
};

const GlobalCommandPaletteContext = createContext<GlobalCommandPaletteContextValue | null>(null);

export function GlobalCommandPaletteProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);

  const toggle = useCallback(() => {
    setOpen((current) => !current);
  }, []);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null;
      const tag = target?.tagName?.toLowerCase();
      const isEditable =
        tag === "input" ||
        tag === "textarea" ||
        tag === "select" ||
        target?.isContentEditable;

      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        toggle();
        return;
      }

      if (event.key === "Escape") {
        setOpen(false);
        return;
      }

      if (isEditable) {
        return;
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [toggle]);

  const value = useMemo(
    () => ({
      open,
      setOpen,
      toggle,
    }),
    [open, toggle],
  );

  return (
    <GlobalCommandPaletteContext.Provider value={value}>{children}</GlobalCommandPaletteContext.Provider>
  );
}

export function useGlobalCommandPalette(): GlobalCommandPaletteContextValue {
  const context = useContext(GlobalCommandPaletteContext);
  if (!context) {
    throw new Error("useGlobalCommandPalette must be used within GlobalCommandPaletteProvider");
  }

  return context;
}
