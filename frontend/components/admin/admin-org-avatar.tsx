"use client";

import { useEffect, useState } from "react";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { apiClient } from "@/lib/api/client";
import { personInitials } from "@/lib/admin/org-chart";
import { cn } from "@/lib/utils";

type Props = {
  name: string;
  photoUrl?: string | null;
  size?: "sm" | "default" | "lg";
  className?: string;
};

function toAdminAvatarApiPath(pathOrUrl: string): string | null {
  const trimmed = pathOrUrl.trim();
  if (!trimmed) return null;
  if (trimmed.startsWith("data:") || trimmed.startsWith("blob:")) return null;

  try {
    if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
      const url = new URL(trimmed);
      const idx = url.pathname.indexOf("/api/v1/");
      if (idx >= 0) {
        return url.pathname.slice(idx + "/api/v1".length) || null;
      }
      return null;
    }
  } catch {
    return null;
  }

  if (trimmed.startsWith("/api/v1/")) {
    return trimmed.slice("/api/v1".length);
  }
  if (trimmed.startsWith("/admin/users/")) {
    return trimmed;
  }
  return null;
}

/** Loads protected org-chart photos with bearer + tenant headers. */
export function AdminOrgAvatar({ name, photoUrl, size = "default", className }: Props) {
  const [src, setSrc] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    let objectUrl: string | null = null;

    async function load() {
      const trimmed = photoUrl?.trim();
      if (!trimmed) {
        setSrc(null);
        return;
      }
      if (trimmed.startsWith("data:") || trimmed.startsWith("blob:")) {
        setSrc(trimmed);
        return;
      }

      const apiPath = toAdminAvatarApiPath(trimmed);
      if (!apiPath) {
        setSrc(trimmed);
        return;
      }

      try {
        const response = await apiClient.get<Blob>(apiPath, { responseType: "blob" });
        if (cancelled) return;
        if (!response.data || response.data.size === 0 || response.data.type.includes("json")) {
          setSrc(null);
          return;
        }
        objectUrl = URL.createObjectURL(response.data);
        setSrc(objectUrl);
      } catch {
        if (!cancelled) setSrc(null);
      }
    }

    void load();
    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [photoUrl]);

  return (
    <Avatar size={size} className={cn(className)}>
      {src ? <AvatarImage src={src} alt="" /> : null}
      <AvatarFallback>{personInitials(name)}</AvatarFallback>
    </Avatar>
  );
}
