"use client";

import { useEffect, useRef, useState, type ReactElement } from "react";
import { ResponsiveContainer } from "recharts";

type Props = {
  height: number;
  children: ReactElement;
  className?: string;
};

/**
 * Avoids Recharts' "width(-1) and height(-1)" warning by mounting
 * ResponsiveContainer only after the host has a positive measured size.
 */
export function DashboardResponsiveChart({ height, children, className }: Props) {
  const hostRef = useRef<HTMLDivElement>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const host = hostRef.current;
    if (!host) {
      return;
    }

    const markReady = () => {
      const { width, height: measuredHeight } = host.getBoundingClientRect();
      if (width > 0 && measuredHeight > 0) {
        setReady(true);
      }
    };

    markReady();

    if (typeof ResizeObserver === "undefined") {
      const timer = window.setTimeout(markReady, 0);
      return () => window.clearTimeout(timer);
    }

    const observer = new ResizeObserver(() => {
      markReady();
    });
    observer.observe(host);

    return () => observer.disconnect();
  }, [height]);

  return (
    <div ref={hostRef} className={className ?? "w-full min-w-0"} style={{ height }}>
      {ready ? (
        <ResponsiveContainer width="100%" height={height} minWidth={0} debounce={50}>
          {children}
        </ResponsiveContainer>
      ) : null}
    </div>
  );
}
