"use client";

import { useEffect, useState } from "react";

import { fetchRolloutFileBlob } from "@/lib/api/modules/rollout-api";
import type { RolloutMediaLink } from "@/modules/rollout/types";

function isImage(mime?: string): boolean {
  return Boolean(mime?.startsWith("image/"));
}

function AuthenticatedThumbnail({ fileId, alt }: { fileId: string; alt: string }) {
  const [src, setSrc] = useState<string | null>(null);

  useEffect(() => {
    let objectUrl: string | null = null;
    let cancelled = false;

    fetchRolloutFileBlob(fileId)
      .then((blob) => {
        if (cancelled) return;
        objectUrl = URL.createObjectURL(blob);
        setSrc(objectUrl);
      })
      .catch(() => {
        if (!cancelled) setSrc(null);
      });

    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [fileId]);

  if (!src) {
    return (
      <div className="flex h-20 w-20 items-center justify-center rounded-md border border-border bg-muted text-[10px] text-muted-foreground">
        Loading
      </div>
    );
  }

  return <img src={src} alt={alt} className="h-20 w-20 rounded-md border border-border object-cover" />;
}

export function RolloutMediaPreview({ items }: { items: RolloutMediaLink[] | null | undefined }) {
  if (!items?.length) return null;

  return (
    <div className="flex flex-wrap gap-3">
      {items.map((item) => (
        <div key={item.file_id} className="space-y-1">
          {isImage(item.mime_type) ? (
            <AuthenticatedThumbnail fileId={item.file_id} alt={item.label ?? "Attachment"} />
          ) : (
            <a
              href={item.url}
              className="inline-flex h-20 w-20 items-center justify-center rounded-md border border-border bg-muted px-2 text-center text-[10px] font-medium text-primary underline-offset-4 hover:underline"
              target="_blank"
              rel="noreferrer"
            >
              PDF
            </a>
          )}
          <p className="max-w-[80px] truncate text-[11px] text-muted-foreground">{item.label ?? "File"}</p>
        </div>
      ))}
    </div>
  );
}
