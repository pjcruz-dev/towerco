"use client";

import { useMutation } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { TenantBrandMark } from "@/components/layout/tenant-brand-mark";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
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
  const [companyAddress, setCompanyAddress] = useState(existing?.company_address ?? "");
  const [companyPhone, setCompanyPhone] = useState(existing?.company_phone ?? "");
  const [companyEmail, setCompanyEmail] = useState(existing?.company_email ?? "");
  const [companyTin, setCompanyTin] = useState(existing?.company_tin ?? "");
  const [showUrls, setShowUrls] = useState(Boolean(existing?.logo_url || existing?.favicon_url));
  const notify = useNotificationStore((state) => state.push);

  useEffect(() => {
    if (!open) {
      return;
    }
    setLogoUrl(existing?.logo_url ?? "");
    setFaviconUrl(existing?.favicon_url ?? "");
    setCompanyAddress(existing?.company_address ?? "");
    setCompanyPhone(existing?.company_phone ?? "");
    setCompanyEmail(existing?.company_email ?? "");
    setCompanyTin(existing?.company_tin ?? "");
    setShowUrls(
      Boolean(existing?.logo_url?.startsWith("https://") || existing?.favicon_url?.startsWith("https://")),
    );
  }, [
    open,
    existing?.logo_url,
    existing?.favicon_url,
    existing?.company_address,
    existing?.company_phone,
    existing?.company_email,
    existing?.company_tin,
  ]);

  const label = tenant.domains[0] ?? tenant.slug ?? tenant.id;

  const uploadMutation = useMutation({
    mutationFn: ({ asset, file }: { asset: "logo" | "favicon"; file: File }) =>
      platformUploadTenantBrandingAsset(tenant.id, asset, file),
    onSuccess: (tokens, variables) => {
      setLogoUrl(tokens.logo_url ?? "");
      setFaviconUrl(tokens.favicon_url ?? "");
      setCompanyAddress(tokens.company_address ?? "");
      setCompanyPhone(tokens.company_phone ?? "");
      setCompanyEmail(tokens.company_email ?? "");
      setCompanyTin(tokens.company_tin ?? "");
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
    const trimmedAddress = companyAddress.trim();
    const trimmedPhone = companyPhone.trim();
    const trimmedEmail = companyEmail.trim();
    const trimmedTin = companyTin.trim();
    const hasLetterhead = Boolean(trimmedAddress || trimmedPhone || trimmedEmail || trimmedTin);
    if (!trimmedLogo && !trimmedFavicon && !hasLetterhead && !existing?.light && !existing?.dark) {
      return null;
    }
    return {
      version: (existing?.version ?? 0) + 1,
      logo_url: trimmedLogo || null,
      favicon_url: trimmedFavicon || null,
      company_address: trimmedAddress || null,
      company_phone: trimmedPhone || null,
      company_email: trimmedEmail || null,
      company_tin: trimmedTin || null,
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
            Logo, favicon, and letterhead for <span className="font-medium text-foreground">{label}</span>.
            Letterhead appears on Dynamic Entity print documents. Users see changes after a refresh.
          </SheetDescription>
        </SheetHeader>

        <div className="flex flex-col gap-4 overflow-y-auto px-4 pb-2">
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

          <div className="space-y-3 border-t border-border pt-4">
            <div>
              <p className="text-sm font-medium text-foreground">Print letterhead</p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Shown on Dynamic Entity print pages for this tenant. Company name uses the tenant
                display name.
              </p>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="tenant-company-address">Address</Label>
              <Textarea
                id="tenant-company-address"
                rows={3}
                placeholder="Unit 1718 High Street South…, BGC, Taguig City, Philippines 1630"
                value={companyAddress}
                onChange={(event) => setCompanyAddress(event.target.value)}
                className="min-h-[4.5rem] resize-y text-sm"
              />
            </div>
            <FormInput
              label="Phone"
              placeholder="+63 2 0000 0000"
              value={companyPhone}
              onChange={(event) => setCompanyPhone(event.target.value)}
              autoComplete="off"
            />
            <FormInput
              label="Email"
              placeholder="contact@example.com"
              value={companyEmail}
              onChange={(event) => setCompanyEmail(event.target.value)}
              autoComplete="off"
            />
            <FormInput
              label="TIN"
              placeholder="987-654-321-000"
              value={companyTin}
              onChange={(event) => setCompanyTin(event.target.value)}
              autoComplete="off"
            />
          </div>
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
