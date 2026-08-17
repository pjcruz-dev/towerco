"use client";

import { useMutation } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { TenantBrandMark } from "@/components/layout/tenant-brand-mark";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformUploadTenantBrandingAsset,
  type PlatformTenantRow,
  type PlatformTenantThemeTokens,
} from "@/lib/api/modules/platform-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenant: PlatformTenantRow;
  isPending: boolean;
  onSave: (themeTokens: PlatformTenantThemeTokens | null) => void;
  onUploaded?: (themeTokens: PlatformTenantThemeTokens) => void;
};

const LOGO_ACCEPT = "image/png,image/jpeg,image/gif,image/webp";
const FAVICON_ACCEPT = `${LOGO_ACCEPT},image/x-icon,.ico`;

export function TenantBrandingSheet({
  open,
  onOpenChange,
  tenant,
  isPending,
  onSave,
  onUploaded,
}: Props) {
  const existing = tenant.theme_tokens;
  const [logoUrl, setLogoUrl] = useState(existing?.logo_url ?? "");
  const [faviconUrl, setFaviconUrl] = useState(existing?.favicon_url ?? "");
  const [showUrls, setShowUrls] = useState(Boolean(existing?.logo_url || existing?.favicon_url));
  const notify = useNotificationStore((state) => state.push);

  useEffect(() => {
    if (!open) {
      return;
    }
    setLogoUrl(existing?.logo_url ?? "");
    setFaviconUrl(existing?.favicon_url ?? "");
    setShowUrls(Boolean(existing?.logo_url?.startsWith("https://") || existing?.favicon_url?.startsWith("https://")));
  }, [open, existing?.logo_url, existing?.favicon_url]);

  const label = tenant.domains[0] ?? tenant.slug ?? tenant.id;

  const uploadMutation = useMutation({
    mutationFn: ({ asset, file }: { asset: "logo" | "favicon"; file: File }) =>
      platformUploadTenantBrandingAsset(tenant.id, asset, file),
    onSuccess: (tokens, variables) => {
      setLogoUrl(tokens.logo_url ?? "");
      setFaviconUrl(tokens.favicon_url ?? "");
      onUploaded?.(tokens);
      notify({
        level: "success",
        title: variables.asset === "logo" ? "Logo uploaded" : "Favicon uploaded",
        message: "Shown in the tenant sidebar and login page after users refresh.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not upload image",
        message: getErrorMessage(error),
      }),
  });

  function buildTokens(clear: boolean): PlatformTenantThemeTokens | null {
    if (clear) {
      return null;
    }
    const trimmedLogo = logoUrl.trim();
    const trimmedFavicon = faviconUrl.trim();
    if (!trimmedLogo && !trimmedFavicon && !existing?.light && !existing?.dark) {
      return null;
    }
    return {
      version: (existing?.version ?? 0) + 1,
      logo_url: trimmedLogo || null,
      favicon_url: trimmedFavicon || null,
      light: existing?.light ?? {},
      dark: existing?.dark ?? {},
    };
  }

  const busy = isPending || uploadMutation.isPending;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Tenant branding</SheetTitle>
          <SheetDescription>
            Logo and favicon for <span className="font-medium text-foreground">{label}</span>. Upload a file or
            paste an HTTPS URL. Users see changes after a refresh.
          </SheetDescription>
        </SheetHeader>

        <div className="flex flex-col gap-4 px-4 pb-2">
          <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/30 p-3">
            <TenantBrandMark size="lg" src={logoUrl.trim() || null} />
            <p className="text-sm text-muted-foreground">
              Preview of the sidebar mark. Empty values fall back to the TowerOS icon.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <label className="cursor-pointer">
              <span className="inline-flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm hover:bg-muted">
                {uploadMutation.isPending && uploadMutation.variables?.asset === "logo"
                  ? "Uploading…"
                  : "Upload logo"}
              </span>
              <input
                type="file"
                accept={LOGO_ACCEPT}
                className="sr-only"
                disabled={busy}
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (file) {
                    uploadMutation.mutate({ asset: "logo", file });
                  }
                  event.target.value = "";
                }}
              />
            </label>
            <label className="cursor-pointer">
              <span className="inline-flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm hover:bg-muted">
                {uploadMutation.isPending && uploadMutation.variables?.asset === "favicon"
                  ? "Uploading…"
                  : "Upload favicon"}
              </span>
              <input
                type="file"
                accept={FAVICON_ACCEPT}
                className="sr-only"
                disabled={busy}
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (file) {
                    uploadMutation.mutate({ asset: "favicon", file });
                  }
                  event.target.value = "";
                }}
              />
            </label>
          </div>
          <p className="text-xs text-muted-foreground">PNG, JPEG, GIF, or WebP. Max 512 KB. ICO allowed for favicon.</p>

          <button
            type="button"
            className="self-start text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
            onClick={() => setShowUrls((openUrls) => !openUrls)}
          >
            {showUrls ? "Hide URL fields" : "Paste a URL instead"}
          </button>

          {showUrls ? (
            <div className="flex flex-col gap-3">
              <FormInput
                label="Logo URL (HTTPS)"
                placeholder="https://cdn.example.com/acme-logo.svg"
                value={logoUrl}
                onChange={(event) => setLogoUrl(event.target.value)}
                autoComplete="off"
              />
              <FormInput
                label="Favicon URL (HTTPS, optional)"
                placeholder="https://cdn.example.com/favicon.ico"
                value={faviconUrl}
                onChange={(event) => setFaviconUrl(event.target.value)}
                autoComplete="off"
              />
            </div>
          ) : null}
        </div>

        <SheetFooter className="mt-0 border-t border-border flex-row flex-wrap gap-2 sm:justify-between">
          <Button type="button" variant="outline" disabled={busy} onClick={() => onSave(null)}>
            Clear branding
          </Button>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="ghost" disabled={busy} onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="button" disabled={busy} onClick={() => onSave(buildTokens(false))}>
              {isPending ? "Saving…" : "Save branding"}
            </Button>
          </div>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
