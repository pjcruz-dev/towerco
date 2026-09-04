"use client";

import { useEffect, useState } from "react";

import { resolveEApprovalAuthenticatedAssetDataUrl } from "@/lib/e-approval/fetch-authenticated-asset";

/**
 * Load a protected E-Approval asset URL via the API client (bearer + tenant)
 * and expose a data URL safe for <img src>.
 */
export function useEApprovalAuthenticatedAssetUrl(
  pathOrUrl: string | null | undefined,
  refreshKey?: string | number,
): { src: string | null; loading: boolean; failed: boolean } {
  const [src, setSrc] = useState<string | null>(null);
  const [loading, setLoading] = useState(Boolean(pathOrUrl?.trim()));
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    const trimmed = pathOrUrl?.trim() ?? "";

    if (!trimmed) {
      setSrc(null);
      setLoading(false);
      setFailed(false);
      return;
    }

    if (trimmed.startsWith("data:") || trimmed.startsWith("blob:")) {
      setSrc(trimmed);
      setLoading(false);
      setFailed(false);
      return;
    }

    setLoading(true);
    setFailed(false);
    setSrc(null);

    void resolveEApprovalAuthenticatedAssetDataUrl(trimmed).then((dataUrl) => {
      if (cancelled) return;
      if (dataUrl) {
        setSrc(dataUrl);
        setFailed(false);
      } else {
        setSrc(null);
        setFailed(true);
      }
      setLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [pathOrUrl, refreshKey]);

  return { src, loading, failed };
}
