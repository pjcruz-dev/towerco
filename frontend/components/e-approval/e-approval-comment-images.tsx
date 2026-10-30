"use client";

import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";

type ImageRef = { id: string; file_name: string };

function isPreviewImage(name: string): boolean {
  return /\.(png|jpe?g|gif|webp)$/i.test(name);
}

export function EApprovalCommentImages({ images }: { images: ImageRef[] }) {
  const [urls, setUrls] = useState<Record<string, string>>({});
  const [openId, setOpenId] = useState<string | null>(null);
  const imageKey = images.map((image) => image.id).join("|");

  useEffect(() => {
    let cancelled = false;
    const created: string[] = [];
    const current = images;

    void (async () => {
      for (const image of current) {
        try {
          const blob = await downloadEApprovalAttachment(image.id);
          const url = URL.createObjectURL(blob);
          created.push(url);
          if (!cancelled) {
            setUrls((prev) => ({ ...prev, [image.id]: url }));
          }
        } catch {
          // A missing file leaves a gap instead of breaking the thread.
        }
      }
    })();

    return () => {
      cancelled = true;
      created.forEach((url) => URL.revokeObjectURL(url));
    };
    // Reload only when the image ids change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [imageKey]);

  if (images.length === 0) {
    return null;
  }

  const open = images.find((image) => image.id === openId) ?? null;
  const openUrl = open ? urls[open.id] : undefined;

  return (
    <>
      <div className="mt-2 flex flex-wrap gap-2">
        {images.map((image) => {
          const url = urls[image.id];
          if (!url) {
            return null;
          }
          if (!isPreviewImage(image.file_name)) {
            return (
              <Button
                key={image.id}
                type="button"
                variant="outline"
                size="sm"
                className="h-8 max-w-56 truncate"
                onClick={() => setOpenId(image.id)}
              >
                {image.file_name || "File"}
              </Button>
            );
          }

          return (
            <button
              key={image.id}
              type="button"
              className="rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              onClick={() => setOpenId(image.id)}
            >
              <img
                src={url}
                alt={image.file_name || "Comment image"}
                className="max-h-40 rounded-md border border-border object-contain"
              />
            </button>
          );
        })}
      </div>
      <Dialog open={open !== null} onOpenChange={(next) => { if (!next) setOpenId(null); }}>
        <DialogContent className="w-[min(calc(100vw-1rem),960px)]">
          <DialogHeader>
            <DialogTitle className="truncate">{open?.file_name || "File"}</DialogTitle>
          </DialogHeader>
          {open && openUrl && isPreviewImage(open.file_name) ? (
            <img src={openUrl} alt={open.file_name || "Comment image"} className="max-h-[75vh] w-full object-contain" />
          ) : null}
          {open && openUrl && !isPreviewImage(open.file_name) ? (
            <iframe title={open.file_name || "File"} src={openUrl} className="h-[75vh] w-full rounded-md border border-border" />
          ) : null}
        </DialogContent>
      </Dialog>
    </>
  );
}
