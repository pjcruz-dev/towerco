"use client";

import * as React from "react";

import { Input } from "@/components/ui/input";
import {
  countCurrencySignificantChars,
  currencyCaretFromSignificantCount,
  formatCurrencyGrouping,
  parseCurrencyTyping,
} from "@/lib/format-currency-input";
import { cn } from "@/lib/utils";

type Props = Omit<React.ComponentProps<"input">, "type" | "value" | "onChange" | "inputMode"> & {
  /** Canonical numeric string without thousand separators (e.g. 12698.95). */
  value: string;
  onChange: (canonical: string) => void;
  /** Max digits after the decimal point (default 2). */
  maxDecimals?: number;
};

/**
 * Money input that shows thousand separators while typing (12,698.95)
 * and emits a clean canonical value (12698.95) via onChange.
 */
function CurrencyInput({
  value,
  onChange,
  maxDecimals = 2,
  className,
  onFocus,
  onBlur,
  disabled,
  readOnly,
  ...props
}: Props) {
  const inputRef = React.useRef<HTMLInputElement | null>(null);
  const focusedRef = React.useRef(false);
  const [display, setDisplay] = React.useState(() => formatCurrencyGrouping(value, maxDecimals));

  React.useEffect(() => {
    if (!focusedRef.current) {
      setDisplay(formatCurrencyGrouping(value, maxDecimals));
    }
  }, [maxDecimals, value]);

  const restoreCaret = React.useCallback((significantCount: number, nextDisplay: string) => {
    requestAnimationFrame(() => {
      const el = inputRef.current;
      if (!el) {
        return;
      }
      const caret = currencyCaretFromSignificantCount(nextDisplay, significantCount);
      el.setSelectionRange(caret, caret);
    });
  }, []);

  return (
    <Input
      {...props}
      ref={inputRef}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      disabled={disabled}
      readOnly={readOnly}
      value={display}
      className={cn("tabular-nums", className)}
      onFocus={(event) => {
        focusedRef.current = true;
        onFocus?.(event);
      }}
      onBlur={(event) => {
        focusedRef.current = false;
        const next = formatCurrencyGrouping(value, maxDecimals);
        setDisplay(next);
        onBlur?.(event);
      }}
      onChange={(event) => {
        if (readOnly || disabled) {
          return;
        }

        const el = event.target;
        const caret = el.selectionStart ?? el.value.length;
        const significantBefore = countCurrencySignificantChars(el.value.slice(0, caret));
        const parsed = parseCurrencyTyping(el.value, maxDecimals);

        setDisplay(parsed.display);
        onChange(parsed.canonical);
        restoreCaret(significantBefore, parsed.display);
      }}
    />
  );
}

export { CurrencyInput };
