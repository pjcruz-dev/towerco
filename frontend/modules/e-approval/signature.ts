export type SignatureInputMode = "draw" | "type" | "upload";

export const SIGNATURE_UPLOAD_ACCEPT = "image/png,image/jpeg,image/webp";
export const SIGNATURE_UPLOAD_MAX_BYTES = 2 * 1024 * 1024;
export const SIGNATURE_UPLOAD_MAX_WIDTH = 900;

export function hasSignatureValue(value: string | null | undefined): boolean {
  return Boolean(value?.trim());
}

export function isDrawnSignature(value: string | null | undefined): boolean {
  return Boolean(value?.startsWith("data:image"));
}

/** PNG/JPEG/WebP signature stored as a data URL (drawn pad or uploaded image). */
export function isImageSignature(value: string | null | undefined): boolean {
  return isDrawnSignature(value);
}

export function isTypedSignature(value: string | null | undefined): boolean {
  return hasSignatureValue(value) && !isDrawnSignature(value);
}

export function signatureModeForValue(value: string | null | undefined): SignatureInputMode {
  return isTypedSignature(value) ? "type" : "draw";
}

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = typeof reader.result === "string" ? reader.result : "";
      if (!result.startsWith("data:image")) {
        reject(new Error("Could not read that image."));
        return;
      }
      resolve(result);
    };
    reader.onerror = () => reject(new Error("Could not read that image."));
    reader.readAsDataURL(file);
  });
}

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error("Could not load that image."));
    img.src = src;
  });
}

/**
 * Convert an uploaded signature image to a PNG data URL.
 * Rejects non-images and files over 2 MB; downscales very wide images.
 */
export async function fileToSignatureDataUrl(file: File): Promise<string> {
  const type = (file.type || "").toLowerCase();
  if (!["image/png", "image/jpeg", "image/jpg", "image/webp"].includes(type)) {
    throw new Error("Use a PNG, JPEG, or WebP image.");
  }

  if (file.size > SIGNATURE_UPLOAD_MAX_BYTES) {
    throw new Error("Image must be 2 MB or smaller.");
  }

  const rawDataUrl = await readFileAsDataUrl(file);
  const img = await loadImage(rawDataUrl);
  const width = img.naturalWidth || img.width;
  const height = img.naturalHeight || img.height;

  if (width <= 0 || height <= 0) {
    throw new Error("Could not load that image.");
  }

  const scale = width > SIGNATURE_UPLOAD_MAX_WIDTH ? SIGNATURE_UPLOAD_MAX_WIDTH / width : 1;
  const targetWidth = Math.max(1, Math.round(width * scale));
  const targetHeight = Math.max(1, Math.round(height * scale));

  const canvas = document.createElement("canvas");
  canvas.width = targetWidth;
  canvas.height = targetHeight;
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    throw new Error("Could not process that image.");
  }

  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, targetWidth, targetHeight);
  ctx.drawImage(img, 0, 0, targetWidth, targetHeight);

  return canvas.toDataURL("image/png");
}
