"use client";

import { useEffect, useRef, useState } from "react";

export function useLocalStorageState<T extends string>(
  key: string,
  defaultValue: T,
  allowedValues: readonly T[],
): [T, (value: T) => void] {
  const [value, setValue] = useState<T>(defaultValue);
  const [hydrated, setHydrated] = useState(false);
  const allowedValuesRef = useRef(allowedValues);
  allowedValuesRef.current = allowedValues;

  useEffect(() => {
    try {
      const stored = localStorage.getItem(key);
      if (stored !== null && (allowedValuesRef.current as readonly string[]).includes(stored)) {
        setValue((current) => (current === stored ? current : (stored as T)));
      }
    } catch {
      // Ignore storage read errors (private mode, blocked storage).
    }
    setHydrated(true);
  }, [key]);

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
