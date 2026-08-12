"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";

import { EApprovalSignaturePad } from "@/components/e-approval/e-approval-signature-pad";
import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { fetchEApprovalMeProfile } from "@/lib/api/modules/e-approval-api";
import {
  fileToSignatureDataUrl,
  hasSignatureValue,
  isImageSignature,
  isTypedSignature,
  SIGNATURE_UPLOAD_ACCEPT,
  signatureModeForValue,
  type SignatureInputMode,
} from "@/modules/e-approval/signature";
import { SIGNATURE_CONSENT_HINT, SIGNATURE_CONSENT_LABEL } from "@/modules/e-approval/signature-consent";

type Props = {
  value: string | null;
  onChange: (value: string | null) => void;
  consentAccepted: boolean;
  onConsentChange: (accepted: boolean) => void;
  disabled?: boolean;
  error?: string | null;
  onErrorChange?: (error: string | null) => void;
  enabled?: boolean;
};

export function EApprovalApprovalSignatureField({
  value,
  onChange,
  consentAccepted,
  onConsentChange,
  disabled,
  error,
  onErrorChange,
  enabled = true,
}: Props) {
  const [mode, setMode] = useState<SignatureInputMode>("draw");
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const profileQuery = useQuery({
    queryKey: ["e-approval", "me", "profile"],
    queryFn: fetchEApprovalMeProfile,
    enabled,
  });

  useEffect(() => {
    if (!enabled || value !== null) {
      return;
    }

    const profileSignature = profileQuery.data?.signature ?? null;
    if (profileSignature) {
      onChange(profileSignature);
      setMode(signatureModeForValue(profileSignature));
    }
  }, [enabled, onChange, profileQuery.data?.signature, value]);

  const handleUpload = async (file: File | null | undefined) => {
    if (!file || disabled) {
      return;
    }

    setUploading(true);
    setUploadError(null);
    onErrorChange?.(null);

    try {
      const dataUrl = await fileToSignatureDataUrl(file);
      onChange(dataUrl);
      setMode("upload");
    } catch (err) {
      const message = err instanceof Error ? err.message : "Could not upload that image.";
      setUploadError(message);
      onErrorChange?.(message);
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    }
  };

  return (
    <div className="space-y-3">
      <div>
        <Label>Your signature</Label>
        <p className="mt-1 text-xs text-muted-foreground">
          {profileQuery.data?.signature
            ? "Loaded from your profile. Update below if needed — it will be saved automatically when you approve."
            : "Required for approval. Draw, type, or upload an image — it will be saved to your profile for next time."}
        </p>
      </div>

      <Tabs
        value={mode}
        onValueChange={(next) => {
          setMode(next as SignatureInputMode);
          setUploadError(null);
        }}
      >
        <TabsList variant="line" className="w-fit justify-start">
          <TabsTrigger value="draw" className="flex-none px-3">
            Draw
          </TabsTrigger>
          <TabsTrigger value="type" className="flex-none px-3">
            Type
          </TabsTrigger>
          <TabsTrigger value="upload" className="flex-none px-3">
            Upload
          </TabsTrigger>
        </TabsList>
        <TabsContent value="draw" className="mt-3">
          <EApprovalSignaturePad
            value={isImageSignature(value) ? value : null}
            onChange={(next) => {
              onChange(next || null);
              onErrorChange?.(null);
              setUploadError(null);
            }}
            disabled={disabled}
            modes={["draw"]}
          />
        </TabsContent>
        <TabsContent value="type" className="mt-3 space-y-3">
          <Textarea
            value={isTypedSignature(value) ? (value ?? "") : ""}
            placeholder="Type your full name"
            rows={2}
            disabled={disabled}
            onChange={(event) => {
              onChange(event.target.value || null);
              onErrorChange?.(null);
              setUploadError(null);
            }}
          />
          <EApprovalSignaturePreview
            value={isTypedSignature(value) ? value : null}
            label="Preview"
            emptyText="Type your name above."
          />
        </TabsContent>
        <TabsContent value="upload" className="mt-3 space-y-3">
          <input
            ref={fileInputRef}
            type="file"
            accept={SIGNATURE_UPLOAD_ACCEPT}
            className="sr-only"
            disabled={disabled || uploading}
            onChange={(event) => {
              void handleUpload(event.target.files?.[0]);
            }}
          />
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || uploading}
              onClick={() => fileInputRef.current?.click()}
            >
              {uploading ? "Uploading…" : isImageSignature(value) ? "Replace image" : "Choose image"}
            </Button>
            {isImageSignature(value) ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={disabled || uploading}
                onClick={() => {
                  onChange(null);
                  setUploadError(null);
                  onErrorChange?.(null);
                }}
              >
                Clear
              </Button>
            ) : null}
          </div>
          <p className="text-xs text-muted-foreground">PNG, JPEG, or WebP · max 2 MB</p>
          <EApprovalSignaturePreview
            value={isImageSignature(value) ? value : null}
            label="Preview"
            emptyText="Upload a clear image of your signature."
          />
          {uploadError ? <p className="text-xs text-destructive">{uploadError}</p> : null}
        </TabsContent>
      </Tabs>

      <label className="flex cursor-pointer items-start gap-2.5 text-sm">
        <Checkbox
          className="mt-0.5"
          checked={consentAccepted}
          onCheckedChange={(checked) => {
            onConsentChange(checked === true);
            onErrorChange?.(null);
          }}
          disabled={disabled}
          aria-describedby="ea-approval-signature-consent-hint"
        />
        <span>
          <span className="font-medium text-foreground">{SIGNATURE_CONSENT_LABEL}</span>
          <span id="ea-approval-signature-consent-hint" className="mt-1 block text-xs text-muted-foreground">
            {SIGNATURE_CONSENT_HINT}
          </span>
        </span>
      </label>

      {error && !uploadError ? <p className="text-xs text-destructive">{error}</p> : null}
    </div>
  );
}

export function validateApprovalSignature(signature: string | null): string | null {
  return hasSignatureValue(signature) ? null : "Add your signature before approving.";
}

export function validateApprovalSignatureConsent(accepted: boolean): string | null {
  return accepted ? null : "Accept the electronic signature consent before approving.";
}
