"use client";

import { useEffect, useRef } from "react";

import { useSidebar } from "@/components/ui/sidebar";
import {
  useNavigationLayoutStore,
  type NavigationLayout,
} from "@/stores/navigation-layout-store";
import { useTenantAppearanceStore } from "@/stores/tenant-appearance-store";

/**
 * Applies a saved or previewed workspace layout after the sidebar cookie hydrates.
 * Closing the customizer without saving restores the layout the person had open.
 */
export function TenantChromeSync() {
  const preview = useTenantAppearanceStore((state) => state.preview);
  const saved = useTenantAppearanceStore((state) => state.saved);
  const setNavigationLayout = useNavigationLayoutStore((state) => state.setLayout);
  const { open, setOpen } = useSidebar();
  const openRef = useRef(open);
  openRef.current = open;
  const setOpenRef = useRef(setOpen);
  setOpenRef.current = setOpen;
  const setNavigationLayoutRef = useRef(setNavigationLayout);
  setNavigationLayoutRef.current = setNavigationLayout;
  const snapshot = useRef<{ layout: NavigationLayout; open: boolean } | null>(null);

  const previewLayout = preview?.layout ?? null;
  const savedLayout = saved?.saved ? saved.layout : null;

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (previewLayout) {
        if (!snapshot.current) {
          snapshot.current = {
            layout: useNavigationLayoutStore.getState().layout,
            open: openRef.current,
          };
        }
        applyLayout(previewLayout, setNavigationLayoutRef.current, setOpenRef.current);
        return;
      }

      if (snapshot.current && !savedLayout) {
        const previous = snapshot.current;
        snapshot.current = null;
        setNavigationLayoutRef.current(previous.layout);
        setOpenRef.current(previous.open);
        return;
      }

      snapshot.current = null;
      if (savedLayout) {
        applyLayout(savedLayout, setNavigationLayoutRef.current, setOpenRef.current);
      }
    }, 0);

    return () => window.clearTimeout(timer);
  }, [previewLayout, savedLayout]);

  return null;
}

function applyLayout(
  layout: "vertical" | "collapsed" | "horizontal",
  setNavigationLayout: (layout: NavigationLayout) => void,
  setOpen: (open: boolean) => void,
) {
  if (layout === "horizontal") {
    setNavigationLayout("navbar");
    return;
  }
  setNavigationLayout("sidebar");
  setOpen(layout === "vertical");
}
