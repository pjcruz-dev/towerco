"use client";

import { QRCodeSVG } from "qrcode.react";

import { cn } from "@/lib/utils";

type Props = {
  otpauthUri: string;
  size?: number;
  className?: string;
  /** Shown under the QR for camera-scan guidance. */
  hint?: string;
};

/**
 * Client-side TOTP QR — encodes otpauth:// locally (never sent to a third-party image API).
 */
export function OtpauthQrCode({
  otpauthUri,
  size = 180,
  className,
  hint = "Scan with Microsoft Authenticator or another TOTP app.",
}: Props) {
  if (!otpauthUri.trim()) {
    return null;
  }

  return (
    <div className={cn("flex flex-col items-start gap-2", className)}>
      <div className="inline-flex rounded-xl border border-border bg-white p-3 shadow-sm">
        <QRCodeSVG
          value={otpauthUri}
          size={size}
          level="M"
          marginSize={1}
          bgColor="#FFFFFF"
          fgColor="#0F172A"
          aria-label="Authenticator QR code"
        />
      </div>
      {hint ? <p className="max-w-[14rem] text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}
