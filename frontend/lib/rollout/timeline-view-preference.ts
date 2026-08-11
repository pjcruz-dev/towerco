const STORAGE_KEY = "toweros.rollout.timeline.preferFocus";

export function readPreferFocusTimeline(): boolean {
  if (typeof window === "undefined") {
    return true;
  }
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
      return true;
    }
    return raw === "1";
  } catch {
    return true;
  }
}

export function writePreferFocusTimeline(preferFocus: boolean): void {
  if (typeof window === "undefined") {
    return;
  }
  try {
    window.localStorage.setItem(STORAGE_KEY, preferFocus ? "1" : "0");
  } catch {
    // ignore quota errors
  }
}
