"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PenLine, Type, Upload } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { EApprovalSignaturePad } from "@/components/e-approval/e-approval-signature-pad";
import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { fetchEApprovalMeProfile, updateEApprovalMeSignature } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import type { EApprovalMeProfile } from "@/modules/e-approval/types";
import {
  hasSignatureValue,
  isDrawnSignature,
  isImageSignature,
  signatureModeForValue,
} from "@/modules/e-approval/signature";
import { SIGNATURE_CONSENT_HINT, SIGNATURE_CONSENT_LABEL } from "@/modules/e-approval/signature-consent";
import { useNotificationStore } from "@/stores/notification-store";

const MAX_SIGNATURE_DATA_URL_LENGTH = 480_000;

type SignatureMode = "draw" | "type" | "upload";

function readImageFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    if (!file.type.startsWith("image/")) {
      reject(new Error("Please choose a PNG or JPEG image."));
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result;
      if (typeof result !== "string") {
        reject(new Error("Could not read the image file."));
        return;
      }
      if (result.length > MAX_SIGNATURE_DATA_URL_LENGTH) {
        reject(new Error("Image is too large. Use a smaller file (under ~350 KB)."));
        return;
      }
      resolve(result);
    };
    reader.onerror = () => reject(new Error("Could not read the image file."));
    reader.readAsDataURL(file);
  });
}

export function EApprovalMeSignaturePanel() {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const uploadInputRef = useRef<HTMLInputElement>(null);
  const [mode, setMode] = useState<SignatureMode>("draw");
  const [draft, setDraft] = useState<string | null>(null);
  const [saved, setSaved] = useState<string | null>(null);
  const [savedSource, setSavedSource] = useState<EApprovalMeProfile["signature_source"]>(null);
  const [uploadFileName, setUploadFileName] = useState<string | null>(null);
  const [isReadingUpload, setIsReadingUpload] = useState(false);
  const [consentAccepted, setConsentAccepted] = useState(false);

  const profileQuery = useQuery({
    queryKey: ["e-approval", "me", "profile"],
    queryFn: fetchEApprovalMeProfile,
  });

  useEffect(() => {
    if (!profileQuery.data) return;

    const profile = profileQuery.data;
    const nextSignature = profile.signature ?? null;
    setSaved(nextSignature);
    setDraft(nextSignature);
    setSavedSource(profile.signature_source ?? null);
    setMode(signatureModeForValue(nextSignature));
    setUploadFileName(null);
    setConsentAccepted(false);
  }, [profileQuery.data]);

  const isDirty = useMemo(() => (draft ?? "") !== (saved ?? ""), [draft, saved]);
  const savingSignature = hasSignatureValue(draft);

  const saveMutation = useMutation({
    mutationFn: () =>
      updateEApprovalMeSignature(hasSignatureValue(draft) ? draft!.trim() : null, {
        signatureConsent: hasSignatureValue(draft) ? true : undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "me", "profile"] });
      setSaved(draft);
      setSavedSource("profile");
      setConsentAccepted(false);
      push({ level: "success", title: "Signature saved" });
    },
    onError: (error) =>
      push({ level: "error", title: "Signature save failed", message: getErrorMessage(error) }),
  });

  const canSave =
    isDirty && (!savingSignature || consentAccepted) && !profileQuery.isLoading && !saveMutation.isPending;

  const handleClear = () => {
    setDraft(null);
    setUploadFileName(null);
    setConsentAccepted(false);
    if (uploadInputRef.current) uploadInputRef.current.value = "";
  };

  const handleUploadFile = async (file: File | undefined) => {
    if (!file) return;
    setIsReadingUpload(true);
    try {
      const dataUrl = await readImageFileAsDataUrl(file);
      setDraft(dataUrl);
      setUploadFileName(file.name);
      setMode("upload");
    } catch (e) {
      push({
        level: "error",
        title: "Upload failed",
        message: e instanceof Error ? e.message : "Could not load image.",
      });
      if (uploadInputRef.current) uploadInputRef.current.value = "";
    } finally {
      setIsReadingUpload(false);
    }
  };

  const sourceLabel =
    savedSource === "last_approval"
      ? "Recovered from your most recent approval"
      : savedSource === "profile"
        ? "Saved to your profile"
        : null;

  const uploadPreview = mode === "upload" && draft && isImageSignature(draft) ? draft : null;

  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div className="border-b border-border px-5 py-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-medium text-foreground">My signature</h2>
            <p className="mt-1 max-w-xl text-sm text-muted-foreground">
              Used when you approve requests and on printed PDF footers. Draw, type, or upload a scan of your
              handwritten signature.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {isDirty ? <Badge variant="secondary">Unsaved changes</Badge> : null}
            {savedSource === "last_approval" && !isDirty ? <Badge variant="outline">From last approval</Badge> : null}
          </div>
        </div>
      </div>

      <div className="grid gap-6 p-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
        <div className="space-y-4">
          <Tabs value={mode} onValueChange={(value) => setMode(value as SignatureMode)}>
            <TabsList variant="line" className="w-fit justify-start gap-1 overflow-x-auto">
              <TabsTrigger value="draw" className="flex-none gap-1.5 px-3">
                <PenLine className="h-3.5 w-3.5" />
                Draw
              </TabsTrigger>
              <TabsTrigger value="type" className="flex-none gap-1.5 px-3">
                <Type className="h-3.5 w-3.5" />
                Type
              </TabsTrigger>
              <TabsTrigger value="upload" className="flex-none gap-1.5 px-3">
                <Upload className="h-3.5 w-3.5" />
                Upload
              </TabsTrigger>
            </TabsList>

            <TabsContent value="draw" className="mt-4">
              <EApprovalSignaturePad
                value={isDrawnSignature(draft) ? draft : null}
                onChange={(value) => {
                  setDraft(value);
                  setUploadFileName(null);
                }}
                disabled={profileQuery.isLoading || saveMutation.isPending}
              />
            </TabsContent>

            <TabsContent value="type" className="mt-4 space-y-3">
              <div className="space-y-2">
                <Label htmlFor="ea-signature-typed">Signature text</Label>
                <Input
                  id="ea-signature-typed"
                  value={isDrawnSignature(draft) || isImageSignature(draft) ? "" : (draft ?? "")}
                  placeholder="Your full name"
                  disabled={profileQuery.isLoading || saveMutation.isPending}
                  onChange={(event) => {
                    setDraft(event.target.value || null);
                    setUploadFileName(null);
                  }}
                />
              </div>
              <EApprovalSignaturePreview
                value={isDrawnSignature(draft) || isImageSignature(draft) ? null : draft}
                label="Typed preview"
                emptyText="Enter your name to preview."
              />
            </TabsContent>

            <TabsContent value="upload" className="mt-4 space-y-4">
              <div className="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-foreground">
                <p>Upload a high-resolution scan of your handwritten signature (PNG or JPEG).</p>
              </div>
              <input
                ref={uploadInputRef}
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                className="sr-only"
                disabled={profileQuery.isLoading || saveMutation.isPending || isReadingUpload}
                onChange={(e) => void handleUploadFile(e.target.files?.[0])}
              />
              <div className="flex flex-wrap items-center gap-2">
                <Button
                  type="button"
                  size="sm"
                  className="gap-1.5"
                  disabled={profileQuery.isLoading || saveMutation.isPending || isReadingUpload}
                  onClick={() => uploadInputRef.current?.click()}
                >
                  <Upload className="h-3.5 w-3.5" />
                  {isReadingUpload ? "Reading file…" : "Upload signature image"}
                </Button>
                {uploadFileName ? (
                  <span className="text-xs text-muted-foreground truncate max-w-[200px]">{uploadFileName}</span>
                ) : null}
              </div>
              {uploadPreview ? (
                <div className="rounded-lg border border-border bg-white p-4 dark:bg-muted/30">
                  <p className="mb-2 text-xs font-medium text-muted-foreground">Preview</p>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={uploadPreview} alt="Uploaded signature preview" className="mx-auto max-h-28 object-contain" />
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No image selected yet.</p>
              )}
            </TabsContent>
          </Tabs>

          <div className="space-y-3 border-t border-border pt-4">
            {savingSignature ? (
              <label className="flex cursor-pointer items-start gap-2.5 text-sm">
                <Checkbox
                  className="mt-0.5"
                  checked={consentAccepted}
                  onCheckedChange={(checked) => setConsentAccepted(checked === true)}
                  disabled={profileQuery.isLoading || saveMutation.isPending}
                  aria-describedby="ea-signature-consent-hint"
                />
                <span>
                  <span className="font-medium text-foreground">{SIGNATURE_CONSENT_LABEL}</span>
                  <span id="ea-signature-consent-hint" className="mt-1 block text-xs text-muted-foreground">
                    {SIGNATURE_CONSENT_HINT}
                  </span>
                </span>
              </label>
            ) : null}
            <div className="flex flex-wrap gap-2">
              <Button size="sm" onClick={() => saveMutation.mutate()} disabled={!canSave}>
                {saveMutation.isPending ? "Saving…" : "Save signature"}
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={handleClear}
                disabled={profileQuery.isLoading || saveMutation.isPending || !hasSignatureValue(draft)}
              >
                Clear
              </Button>
            </div>
          </div>
        </div>

        <aside className="space-y-4 rounded-xl border border-border/70 bg-muted/20 p-4">
          <EApprovalSignaturePreview
            value={saved}
            label="Current signature"
            emptyText="No signature yet. Draw, type, or upload one, then save — or approve a request to capture it automatically."
          />
          {sourceLabel ? <p className="text-xs text-muted-foreground">{sourceLabel}</p> : null}
          <ul className="space-y-2 text-xs text-muted-foreground">
            <li>Upload works best for scanned signatures on white paper.</li>
            <li>Approving a request also saves the signature you use for next time.</li>
            <li>Drawn or uploaded images appear on PDF approval footers.</li>
          </ul>
        </aside>
      </div>
    </section>
  );
}
