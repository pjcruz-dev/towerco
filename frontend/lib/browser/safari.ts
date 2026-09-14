/** Lightweight browser capability helpers (Safari-focused). */

export function isSafariBrowser(): boolean {
  if (typeof navigator === "undefined") {
    return false;
  }
  const ua = navigator.userAgent;
  return /safari/i.test(ua) && !/chrome|chromium|android|crios|fxios/i.test(ua);
}

/**
 * Safari PDF viewer often fails when blob URLs include Chrome-style fragments
 * (`#toolbar=0`). Use a plain URL on Safari.
 */
export function pdfObjectUrlForPreview(url: string): string {
  return isSafariBrowser() ? url : `${url}#toolbar=0&navpanes=0&scrollbar=0`;
}

/** Prefer smooth scrolling when supported; never throw on older Safari. */
export function safeScrollIntoView(
  el: Element | null | undefined,
  options?: ScrollIntoViewOptions,
): void {
  if (!el) {
    return;
  }
  try {
    el.scrollIntoView(options);
  } catch {
    try {
      el.scrollIntoView(true);
    } catch {
      // ignore
    }
  }
}
