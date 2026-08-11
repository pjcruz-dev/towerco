"use client";

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
import type { PlatformTenantRow, PlatformTenantThemeTokens } from "@/lib/api/modules/platform-api";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenant: PlatformTenantRow;
  isPending: boolean;
  onSave: (themeTokens: PlatformTenantThemeTokens | null) => void;
};

export function TenantBrandingSheet({ open, onOpenChange, tenant, isPending, onSave }: Props) {
  const existing = tenant.theme_tokens;
  const [logoUrl, setLogoUrl] = useState(existing?.logo_url ?? "");
  const [faviconUrl, setFaviconUrl] = useState(existing?.favicon_url ?? "");

  useEffect(() => {
    if (!open) {
      return;
    }
    setLogoUrl(existing?.logo_url ?? "");
    setFaviconUrl(existing?.favicon_url ?? "");
  }, [open, existing?.logo_url, existing?.favicon_url]);

  const label = tenant.domains[0] ?? tenant.slug ?? tenant.id;

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

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Tenant branding</SheetTitle>
          <SheetDescription>
            Set logo and favicon for <span className="font-medium text-foreground">{label}</span>. URLs must
            use HTTPS. Shown in the tenant sidebar and login page after users refresh.
          </SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-6 overflow-y-auto px-1 py-2">
          <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/30 p-4">
            <TenantBrandMark size="lg" />
            <p className="text-sm text-muted-foreground">
              Preview uses the logo URL below when saved. Sidebar falls back to the TowerOS mark when empty.
            </p>
          </div>

          <FormInput
            label="Logo URL (HTTPS)"
            placeholder="https://cdn.example.com/acme-logo.svg"
            value={logoUrl}
            onChange={(e) => setLogoUrl(e.target.value)}
            autoComplete="off"
          />
          <FormInput
            label="Favicon URL (HTTPS, optional)"
            placeholder="https://cdn.example.com/favicon.ico"
            value={faviconUrl}
            onChange={(e) => setFaviconUrl(e.target.value)}
            autoComplete="off"
          />
        </div>

        <SheetFooter className="flex-row flex-wrap gap-2 sm:justify-between">
          <Button
            type="button"
            variant="outline"
            disabled={isPending}
            onClick={() => onSave(null)}
          >
            Clear branding
          </Button>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="ghost" disabled={isPending} onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="button" disabled={isPending} onClick={() => onSave(buildTokens(false))}>
              {isPending ? "Saving…" : "Save branding"}
            </Button>
          </div>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
