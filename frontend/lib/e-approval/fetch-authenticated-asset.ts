import { apiClient } from "@/lib/api/client";

/**
 * Normalize an E-Approval asset URL/path to an apiClient-relative path
 * (e.g. `/e-approval/forms/{id}/subsidiary-logos/ATC`).
 */
export function toEApprovalApiAssetPath(pathOrUrl: string): string | null {
  const trimmed = pathOrUrl.trim();
  if (!trimmed || trimmed.startsWith("data:") || trimmed.startsWith("blob:")) {
    return null;
  }

  try {
    if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
      const url = new URL(trimmed);
      const idx = url.pathname.indexOf("/api/v1/");
      if (idx >= 0) {
        return url.pathname.slice(idx + "/api/v1".length) || null;
      }
      return null;
    }
  } catch {
    return null;
  }

  if (trimmed.startsWith("/api/v1/")) {
    return trimmed.slice("/api/v1".length);
  }

  if (trimmed.startsWith("/e-approval/")) {
    return trimmed;
  }

  return null;
}

/** Fetch a protected E-Approval image/asset with bearer + tenant headers. */
export async function fetchEApprovalAuthenticatedAssetBlob(pathOrUrl: string): Promise<Blob> {
  const apiPath = toEApprovalApiAssetPath(pathOrUrl);
  if (!apiPath) {
    throw new Error("Unsupported asset path");
  }

  const response = await apiClient.get<Blob>(apiPath, { responseType: "blob" });
  return response.data;
}

export async function blobToDataUrl(blob: Blob): Promise<string> {
  return await new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ""));
    reader.onerror = () => reject(reader.error ?? new Error("Failed to read blob"));
    reader.readAsDataURL(blob);
  });
}

/**
 * Resolve protected API logo URLs into data URLs so <img> / print HTML work
 * without bearer headers on the image request.
 */
export async function resolveEApprovalAuthenticatedAssetDataUrl(
  pathOrUrl: string | null | undefined,
): Promise<string | null> {
  const trimmed = pathOrUrl?.trim();
  if (!trimmed) {
    return null;
  }
  if (trimmed.startsWith("data:") || trimmed.startsWith("blob:")) {
    return trimmed;
  }
  if (!toEApprovalApiAssetPath(trimmed)) {
    return trimmed;
  }

  try {
    const blob = await fetchEApprovalAuthenticatedAssetBlob(trimmed);
    if (!blob || blob.size === 0) {
      return null;
    }
    // Avoid treating JSON error bodies as images.
    if (blob.type && blob.type.includes("json")) {
      return null;
    }
    return await blobToDataUrl(blob);
  } catch {
    return null;
  }
}

export async function hydrateEApprovalPrintLogoUrls<
  T extends {
    brand_logo_url?: string | null;
    subsidiary_logos?: Record<string, string>;
    template?: Record<string, unknown>;
  },
>(payload: T): Promise<T> {
  const brand = await resolveEApprovalAuthenticatedAssetDataUrl(payload.brand_logo_url);

  const sourceLogos: Record<string, string> = {
    ...(payload.subsidiary_logos ?? {}),
  };
  const template = payload.template;
  if (template && typeof template === "object") {
    const fromTemplate = template.subsidiary_logos;
    if (fromTemplate && typeof fromTemplate === "object") {
      for (const [code, url] of Object.entries(fromTemplate as Record<string, unknown>)) {
        if (typeof url === "string" && url.trim() && !sourceLogos[code]) {
          sourceLogos[code] = url;
        }
      }
    }
  }

  const hydratedLogos: Record<string, string> = {};
  await Promise.all(
    Object.entries(sourceLogos).map(async ([code, url]) => {
      const dataUrl = await resolveEApprovalAuthenticatedAssetDataUrl(url);
      if (dataUrl) {
        hydratedLogos[code] = dataUrl;
      }
    }),
  );

  const nextTemplate =
    template && typeof template === "object"
      ? {
          ...template,
          subsidiary_logos: {
            ...((template.subsidiary_logos as Record<string, string> | undefined) ?? {}),
            ...hydratedLogos,
          },
        }
      : template;

  return {
    ...payload,
    brand_logo_url: brand ?? payload.brand_logo_url ?? null,
    subsidiary_logos: {
      ...(payload.subsidiary_logos ?? {}),
      ...hydratedLogos,
    },
    template: nextTemplate,
  };
}
