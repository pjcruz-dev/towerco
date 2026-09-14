"use client";

import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { EApprovalAuthenticatedImage } from "@/components/e-approval/e-approval-authenticated-image";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  clearEApprovalTenantSubsidiaryLogo,
  fetchEApprovalTenantSubsidiaryLogos,
  registerEApprovalTenantSubsidiaryCode,
  removeEApprovalTenantSubsidiaryCode,
  uploadEApprovalTenantSubsidiaryLogo,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { normalizeSubsidiaryCode } from "@/modules/e-approval/print-template-types";
import { useNotificationStore } from "@/stores/notification-store";

/**
 * Tenant-wide subsidiary letterhead logos (E-Forms Settings).
 * All forms inherit these for {{system.subsidiary_logo}}.
 */
export function EApprovalTenantSubsidiaryLogosPanel() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [newCode, setNewCode] = useState("");
  const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const catalogQuery = useQuery({
    queryKey: ["e-approval", "tenant-subsidiary-logos"],
    queryFn: fetchEApprovalTenantSubsidiaryLogos,
  });

  const codes = (catalogQuery.data?.subsidiary_codes ?? []).map(String);
  const logos = catalogQuery.data?.subsidiary_logos ?? {};

  const uploadMutation = useMutation({
    mutationFn: ({ code, file }: { code: string; file: File }) =>
      uploadEApprovalTenantSubsidiaryLogo(code, file),
    onSuccess: (result) => {
      queryClient.setQueryData(["e-approval", "tenant-subsidiary-logos"], {
        subsidiary_codes: result.subsidiary_codes,
        subsidiary_logos: result.subsidiary_logos,
      });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout"] });
      push({ level: "success", title: `${result.code} logo uploaded` });
    },
    onError: (e) => push({ level: "error", title: "Logo upload failed", message: getErrorMessage(e) }),
  });

  const clearMutation = useMutation({
    mutationFn: (code: string) => clearEApprovalTenantSubsidiaryLogo(code),
    onSuccess: (result) => {
      queryClient.setQueryData(["e-approval", "tenant-subsidiary-logos"], {
        subsidiary_codes: result.subsidiary_codes,
        subsidiary_logos: result.subsidiary_logos,
      });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout"] });
      push({ level: "success", title: `${result.code} logo cleared` });
    },
    onError: (e) => push({ level: "error", title: "Could not clear logo", message: getErrorMessage(e) }),
  });

  const addMutation = useMutation({
    mutationFn: (code: string) => registerEApprovalTenantSubsidiaryCode(code),
    onSuccess: (result) => {
      setNewCode("");
      queryClient.setQueryData(["e-approval", "tenant-subsidiary-logos"], {
        subsidiary_codes: result.subsidiary_codes,
        subsidiary_logos: result.subsidiary_logos,
      });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout"] });
      push({ level: "success", title: `${result.code} added` });
    },
    onError: (e) => push({ level: "error", title: "Could not add subsidiary", message: getErrorMessage(e) }),
  });

  const removeMutation = useMutation({
    mutationFn: (code: string) => removeEApprovalTenantSubsidiaryCode(code),
    onSuccess: (result) => {
      queryClient.setQueryData(["e-approval", "tenant-subsidiary-logos"], {
        subsidiary_codes: result.subsidiary_codes,
        subsidiary_logos: result.subsidiary_logos,
      });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout"] });
      push({ level: "success", title: `${result.code} removed` });
    },
    onError: (e) => push({ level: "error", title: "Could not remove subsidiary", message: getErrorMessage(e) }),
  });

  const busy =
    uploadMutation.isPending ||
    clearMutation.isPending ||
    addMutation.isPending ||
    removeMutation.isPending;

  const onAddCode = () => {
    const code = normalizeSubsidiaryCode(newCode);
    if (!code) {
      push({
        level: "error",
        title: "Invalid code",
        message: "Use 1–24 characters: letters, numbers, _ or -.",
      });
      return;
    }
    if (codes.includes(code)) {
      push({ level: "error", title: "Already added", message: `${code} is already in the list.` });
      return;
    }
    addMutation.mutate(code);
  };

  return (
    <div className="space-y-3">
      <p className="text-xs text-muted-foreground">
        Upload once for the whole tenant. Every form print design inherits these logos via{" "}
        <code className="rounded bg-muted px-1">{"{{system.subsidiary_logo}}"}</code> from the form’s Subsidiary
        field.
      </p>

      <div className="flex flex-wrap items-end gap-2">
        <div className="min-w-[10rem] flex-1 space-y-1.5 sm:max-w-xs">
          <Label htmlFor="ea-tenant-subsidiary-code">Add subsidiary code</Label>
          <Input
            id="ea-tenant-subsidiary-code"
            className="h-9 uppercase"
            placeholder="e.g. ATC, ADIC, NEWCO"
            value={newCode}
            disabled={busy}
            onChange={(e) => setNewCode(e.target.value.toUpperCase())}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                onAddCode();
              }
            }}
          />
        </div>
        <Button type="button" size="sm" disabled={busy || !newCode.trim()} onClick={onAddCode}>
          {addMutation.isPending ? "Adding…" : "Add"}
        </Button>
      </div>

      {catalogQuery.isLoading ? (
        <p className="text-sm text-muted-foreground">Loading subsidiaries…</p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {codes.map((code) => {
            const logoPath = logos[code] ?? null;
            return (
              <div key={code} className="rounded-lg border border-border bg-background p-3">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-sm font-medium text-foreground">{code}</p>
                    <p className="text-[11px] text-muted-foreground">Shown when Subsidiary = {code}</p>
                  </div>
                  <div className="flex flex-wrap justify-end gap-1">
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={() => fileInputRefs.current[code]?.click()}
                    >
                      {logoPath ? "Replace" : "Upload"}
                    </Button>
                    {logoPath ? (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={busy}
                        onClick={() => clearMutation.mutate(code)}
                      >
                        Clear
                      </Button>
                    ) : null}
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={busy}
                      onClick={() => removeMutation.mutate(code)}
                    >
                      Remove
                    </Button>
                  </div>
                </div>
                <input
                  ref={(el) => {
                    fileInputRefs.current[code] = el;
                  }}
                  type="file"
                  accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) uploadMutation.mutate({ code, file });
                    e.target.value = "";
                  }}
                />
                <div className="mt-3 flex min-h-[56px] items-center justify-center rounded-md border border-dashed border-border bg-muted/30 px-3 py-2">
                  <EApprovalAuthenticatedImage
                    pathOrUrl={logoPath}
                    alt={`${code} logo`}
                    refreshKey={logoPath ?? code}
                  />
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
