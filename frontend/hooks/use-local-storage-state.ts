"use client";

import { useEffect, useState } from "react";

export function useLocalStorageState<T extends string>(
  key: string,
  defaultValue: T,
  allowedValues: readonly T[],
): [T, (value: T) => void] {
  const [value, setValue] = useState<T>(defaultValue);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem(key);
      if (stored !== null && (allowedValues as readonly string[]).includes(stored)) {
        setValue(stored as T);
      }
    } catch {
      // Ignore storage read errors (private mode, blocked storage).
    }
    setHydrated(true);
  }, [allowedValues, key]);

  useEffect(() => {
    if (!hydrated) {
      return;
    }

    try {
      localStorage.setItem(key, value);
    } catch {
      // Ignore storage write errors.
    }
  }, [hydrated, key, value]);

  return [value, setValue];
}
