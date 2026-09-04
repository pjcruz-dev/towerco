"use client";

import { useEApprovalAuthenticatedAssetUrl } from "@/hooks/use-e-approval-authenticated-asset-url";

type Props = {
  pathOrUrl: string | null | undefined;
  alt: string;
  className?: string;
  refreshKey?: string | number;
  emptyLabel?: string;
};

export function EApprovalAuthenticatedImage({
  pathOrUrl,
  alt,
  className = "max-h-12 max-w-full object-contain",
  refreshKey,
  emptyLabel = "No logo uploaded",
}: Props) {
  const { src, loading, failed } = useEApprovalAuthenticatedAssetUrl(pathOrUrl, refreshKey);

  if (!pathOrUrl?.trim()) {
    return <span className="text-xs text-muted-foreground">{emptyLabel}</span>;
  }

  if (loading) {
    return <span className="text-xs text-muted-foreground">Loading…</span>;
  }

  if (failed || !src) {
    return <span className="text-xs text-muted-foreground">Logo unavailable</span>;
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} className={className} />
  );
}
