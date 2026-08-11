"use client";

import { useEffect, useState } from "react";

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/**
 * Persist a JSON-serializable value to localStorage (SSR-safe hydrate).
 * Pass `key: null` to keep state in memory only (no read/write).
 */
export function useLocalStorageJsonState<T>(
  key: string | null,
  defaultValue: T,
  isValid?: (value: unknown) => value is T,
): [T, (value: T | ((prev: T) => T)) => void] {
  const [value, setValue] = useState<T>(defaultValue);
  const [hydrated, setHydrated] = useState(() => key === null);

  useEffect(() => {
    if (key === null) {
      setHydrated(true);
      return;
    }

    try {
      const stored = localStorage.getItem(key);
      if (stored !== null) {
        const parsed: unknown = JSON.parse(stored);
        if (isValid ? isValid(parsed) : true) {
          setValue(parsed as T);
        }
      }
    } catch {
      // Ignore storage read errors (private mode, blocked storage).
    }
    setHydrated(true);
  }, [isValid, key]);

  useEffect(() => {
    if (!hydrated || key === null) {
      return;
    }

    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      // Ignore storage write errors.
    }
  }, [hydrated, key, value]);

  return [value, setValue];
}

export function isVisibilityState(value: unknown): value is Record<string, boolean> {
  if (!isPlainObject(value)) return false;
  return Object.values(value).every((entry) => typeof entry === "boolean");
}
