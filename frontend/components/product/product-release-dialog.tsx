"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { PRODUCT_RELEASE_SEEN_KEY } from "@/content/release-notes";
import { useProductRelease } from "@/hooks/use-product-release";
import { putUserUiPreference } from "@/lib/api/modules/user-ui-preferences-api";

export function ProductReleaseDialog() {
  const { release, unseen } = useProductRelease();
  const [open, setOpen] = useState(false);
  const [dismissedVersion, setDismissedVersion] = useState<string | null>(null);
  const queryClient = useQueryClient();
  const pending = unseen && dismissedVersion !== release.version;

  useEffect(() => {
    if (pending) {
      setOpen(true);
    }
  }, [pending]);

  const dismiss = useMutation({
    mutationFn: () => putUserUiPreference(PRODUCT_RELEASE_SEEN_KEY, { version: release.version }),
    onSettled: () => {
      void queryClient.invalidateQueries({
        queryKey: ["me", "ui-preference", PRODUCT_RELEASE_SEEN_KEY],
      });
    },
  });

  const close = () => {
    setDismissedVersion(release.version);
    setOpen(false);
    if (unseen) {
      dismiss.mutate();
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          close();
        }
      }}
    >
      <DialogContent className="w-[min(calc(100vw-2rem),36rem)]">
        <DialogHeader>
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {release.version}
          </p>
          <DialogTitle className="mt-1">{release.title}</DialogTitle>
          {release.summary ? <DialogDescription>{release.summary}</DialogDescription> : null}
        </DialogHeader>
        <DialogBody className="space-y-4">
          {release.sections.map((section) => (
            <section key={`${section.heading}-${section.body.slice(0, 24)}`}>
              {section.heading ? (
                <h3 className="text-sm font-medium text-foreground">{section.heading}</h3>
              ) : null}
              {section.body ? (
                <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{section.body}</p>
              ) : null}
            </section>
          ))}
        </DialogBody>
        <DialogFooter>
          <Button type="button" onClick={close} disabled={dismiss.isPending}>
            Got it
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
