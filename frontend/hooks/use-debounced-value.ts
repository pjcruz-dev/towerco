"use client";

import { useEffect, useRef, useState } from "react";

/**
 * Debounces `value` by `delayMs`. When the debounced value commits (differs from the last committed value),
 * invokes `onDebouncedCommit` inside the timer callback (not synchronously in an effect body).
 */
export function useDebouncedValue<T>(value: T, delayMs: number, onDebouncedCommit?: () => void): T {
  const [debounced, setDebounced] = useState(value);
  const lastCommitted = useRef(value);
  const onCommitRef = useRef(onDebouncedCommit);

  useEffect(() => {
    onCommitRef.current = onDebouncedCommit;
  }, [onDebouncedCommit]);

  useEffect(() => {
    const t = window.setTimeout(() => {
      if (Object.is(lastCommitted.current, value)) {
        return;
      }
      lastCommitted.current = value;
      setDebounced(value);
      onCommitRef.current?.();
    }, delayMs);
    return () => window.clearTimeout(t);
  }, [value, delayMs]);

  return debounced;
}
