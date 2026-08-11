"use client";

import { useCallback, useEffect, useRef, useState } from "react";

import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { isSignatureDataUrl } from "@/modules/e-approval/field-type-options";
import {
  fileToSignatureDataUrl,
  SIGNATURE_UPLOAD_ACCEPT,
  type SignatureInputMode,
} from "@/modules/e-approval/signature";
import { cn } from "@/lib/utils";

type Props = {
  value: string | null;
  onChange: (value: string) => void;
  disabled?: boolean;
  placeholder?: string;
  /** Which input modes to show. Defaults to draw + type + upload. */
  modes?: SignatureInputMode[];
};

const DEFAULT_MODES: SignatureInputMode[] = ["draw", "type", "upload"];

export function EApprovalSignaturePad({
  value,
  onChange,
  disabled,
  placeholder,
  modes = DEFAULT_MODES,
}: Props) {
  const safeValue = value ?? "";
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const drawingRef = useRef(false);
  const availableModes = modes.length > 0 ? modes : DEFAULT_MODES;
  const [mode, setMode] = useState<SignatureInputMode>(() => {
    if (safeValue && !isSignatureDataUrl(safeValue) && availableModes.includes("type")) {
      return "type";
    }
    if (availableModes.includes("draw")) {
      return "draw";
    }
    return availableModes[0]!;
  });
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (!availableModes.includes(mode)) {
      setMode(availableModes[0]!);
    }
  }, [availableModes, mode]);

  const exportCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) {
      return;
    }

    const blank = document.createElement("canvas");
    blank.width = canvas.width;
    blank.height = canvas.height;
    if (canvas.toDataURL() === blank.toDataURL()) {
      onChange("");
      return;
    }

    onChange(canvas.toDataURL("image/png"));
  }, [onChange]);

  const resizeCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) {
      return;
    }

    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(rect.width * ratio));
    canvas.height = Math.max(1, Math.floor(rect.height * ratio));

    const ctx = canvas.getContext("2d");
    if (!ctx) {
      return;
    }

    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.strokeStyle = "#0f172a";
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";

    if (isSignatureDataUrl(safeValue)) {
      const img = new Image();
      img.onload = () => {
        ctx.clearRect(0, 0, rect.width, rect.height);
        // Preserve aspect ratio — never stretch a saved signature into the wide pad.
        const srcW = img.naturalWidth || img.width;
        const srcH = img.naturalHeight || img.height;
        if (srcW <= 0 || srcH <= 0) {
          return;
        }
        const scale = Math.min(rect.width / srcW, rect.height / srcH);
        const drawW = srcW * scale;
        const drawH = srcH * scale;
        const dx = (rect.width - drawW) / 2;
        const dy = (rect.height - drawH) / 2;
        ctx.drawImage(img, dx, dy, drawW, drawH);
      };
      img.src = safeValue;
    }
  }, [safeValue]);

  useEffect(() => {
    if (mode !== "draw") {
      return;
    }
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    return () => window.removeEventListener("resize", resizeCanvas);
  }, [resizeCanvas, mode]);

  const pointerPos = (event: React.PointerEvent<HTMLCanvasElement>) => {
    const canvas = canvasRef.current!;
    const rect = canvas.getBoundingClientRect();

    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };
  };

  const startDraw = (event: React.PointerEvent<HTMLCanvasElement>) => {
    if (disabled) {
      return;
    }

    const ctx = canvasRef.current?.getContext("2d");
    if (!ctx) {
      return;
    }

    drawingRef.current = true;
    canvasRef.current?.setPointerCapture(event.pointerId);
    const { x, y } = pointerPos(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
  };

  const draw = (event: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current || disabled) {
      return;
    }

    const ctx = canvasRef.current?.getContext("2d");
    if (!ctx) {
      return;
    }

    const { x, y } = pointerPos(event);
    ctx.lineTo(x, y);
    ctx.stroke();
  };

  const endDraw = (event: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current) {
      return;
    }

    drawingRef.current = false;
    canvasRef.current?.releasePointerCapture(event.pointerId);
    exportCanvas();
  };

  const clearPad = () => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (canvas && ctx) {
      const rect = canvas.getBoundingClientRect();
      ctx.clearRect(0, 0, rect.width, rect.height);
    }
    onChange("");
    setUploadError(null);
  };

  const handleUpload = async (file: File | null | undefined) => {
    if (!file || disabled) {
      return;
    }

    setUploading(true);
    setUploadError(null);
    try {
      const dataUrl = await fileToSignatureDataUrl(file);
      onChange(dataUrl);
      setMode("upload");
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : "Could not upload that image.");
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    }
  };

  const showModeSwitcher = availableModes.length > 1;

  return (
    <div className="space-y-2">
      {showModeSwitcher ? (
        <div className="flex flex-wrap gap-2">
          {availableModes.includes("draw") ? (
            <Button
              type="button"
              size="sm"
              variant={mode === "draw" ? "default" : "outline"}
              disabled={disabled}
              onClick={() => setMode("draw")}
            >
              Draw
            </Button>
          ) : null}
          {availableModes.includes("type") ? (
            <Button
              type="button"
              size="sm"
              variant={mode === "type" ? "default" : "outline"}
              disabled={disabled}
              onClick={() => setMode("type")}
            >
              Type
            </Button>
          ) : null}
          {availableModes.includes("upload") ? (
            <Button
              type="button"
              size="sm"
              variant={mode === "upload" ? "default" : "outline"}
              disabled={disabled}
              onClick={() => setMode("upload")}
            >
              Upload
            </Button>
          ) : null}
          {mode === "draw" || (mode === "upload" && isSignatureDataUrl(safeValue)) ? (
            <Button type="button" size="sm" variant="ghost" disabled={disabled} onClick={clearPad}>
              Clear
            </Button>
          ) : null}
        </div>
      ) : mode === "draw" ? (
        <div className="flex justify-end">
          <Button type="button" size="sm" variant="ghost" disabled={disabled} onClick={clearPad}>
            Clear
          </Button>
        </div>
      ) : null}

      {mode === "draw" ? (
        <div
          className={cn(
            "overflow-hidden rounded-lg border border-input bg-white dark:bg-slate-50",
            disabled && "opacity-60",
          )}
        >
          <canvas
            ref={canvasRef}
            className="h-32 w-full touch-none cursor-crosshair"
            aria-label="Draw your signature"
            onPointerDown={startDraw}
            onPointerMove={draw}
            onPointerUp={endDraw}
            onPointerLeave={endDraw}
          />
        </div>
      ) : null}

      {mode === "type" ? (
        <Textarea
          disabled={disabled}
          placeholder={placeholder ?? "Type your full name"}
          className="min-h-[80px]"
          value={isSignatureDataUrl(safeValue) ? "" : safeValue}
          onChange={(e) => onChange(e.target.value)}
        />
      ) : null}

      {mode === "upload" ? (
        <div className="space-y-3">
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
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={disabled || uploading}
            onClick={() => fileInputRef.current?.click()}
          >
            {uploading ? "Uploading…" : isSignatureDataUrl(safeValue) ? "Replace image" : "Choose image"}
          </Button>
          <p className="text-xs text-muted-foreground">PNG, JPEG, or WebP · max 2 MB</p>
          <EApprovalSignaturePreview
            value={isSignatureDataUrl(safeValue) ? safeValue : null}
            label="Preview"
            emptyText="Upload a clear image of your signature."
          />
          {uploadError ? <p className="text-xs text-destructive">{uploadError}</p> : null}
        </div>
      ) : null}

      {isSignatureDataUrl(safeValue) && mode === "draw" ? (
        <p className="text-xs text-muted-foreground">Signature captured. Clear to redraw.</p>
      ) : null}
    </div>
  );
}
