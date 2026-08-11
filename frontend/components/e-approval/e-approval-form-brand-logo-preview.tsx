"use client";

import { useEffect, useState } from "react";

import { apiClient } from "@/lib/api/client";

type Props = {
  formId: string;
  refreshKey?: string | number;
};

export function EApprovalFormBrandLogoPreview({ formId, refreshKey }: Props) {
  const [src, setSrc] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let objectUrl: string | null = null;
    let cancelled = false;
    setSrc(null);
    setFailed(false);

    apiClient
      .get(`/e-approval/forms/${formId}/logo`, {
        responseType: "blob",
        params: refreshKey !== undefined ? { v: refreshKey } : undefined,
      })
      .then((response) => {
        if (cancelled) {
          return;
        }
        objectUrl = URL.createObjectURL(response.data);
        setSrc(objectUrl);
      })
      .catch(() => {
        if (!cancelled) {
          setFailed(true);
        }
      });

    return () => {
      cancelled = true;
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [formId, refreshKey]);

  if (failed) {
    return <span className="text-sm text-muted-foreground">No logo uploaded.</span>;
  }

  if (!src) {
    return <span className="text-sm text-muted-foreground">Loading logo…</span>;
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt="Form logo" className="h-12 max-w-[200px] object-contain" />
  );
}
