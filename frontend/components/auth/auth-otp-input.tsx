"use client";

import { useEffect, useRef, useState } from "react";

import { cn } from "@/lib/utils";

type AuthOtpInputProps = {
  length?: number;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  autoFocus?: boolean;
  "aria-invalid"?: boolean;
};

/**
 * Six-box OTP entry (verification screens). Digits only; supports paste.
 */
export function AuthOtpInput({
  length = 6,
  value,
  onChange,
  disabled = false,
  autoFocus = false,
  "aria-invalid": ariaInvalid,
}: AuthOtpInputProps) {
  const digits = value.replace(/\D/g, "").slice(0, length).split("");
  while (digits.length < length) {
    digits.push("");
  }

  const refs = useRef<Array<HTMLInputElement | null>>([]);
  const [focused, setFocused] = useState(0);

  useEffect(() => {
    if (autoFocus) {
      refs.current[0]?.focus();
    }
  }, [autoFocus]);

  const emit = (nextDigits: string[]) => {
    onChange(nextDigits.join("").replace(/\D/g, "").slice(0, length));
  };

  const setDigit = (index: number, char: string) => {
    const next = [...digits];
    next[index] = char.replace(/\D/g, "").slice(-1);
    emit(next);
    if (char && index < length - 1) {
      refs.current[index + 1]?.focus();
      setFocused(index + 1);
    }
  };

  return (
    <div className="flex items-center justify-center gap-2 sm:gap-2.5" role="group" aria-label="Verification code">
      {digits.map((digit, index) => (
        <span key={index} className="contents">
          {index === Math.floor(length / 2) ? (
            <span className="px-0.5 text-muted-foreground" aria-hidden>
              –
            </span>
          ) : null}
          <input
            ref={(el) => {
              refs.current[index] = el;
            }}
            type="text"
            inputMode="numeric"
            autoComplete={index === 0 ? "one-time-code" : "off"}
            maxLength={1}
            disabled={disabled}
            aria-invalid={ariaInvalid}
            aria-label={`Digit ${index + 1}`}
            value={digit}
            onFocus={() => setFocused(index)}
            onChange={(e) => setDigit(index, e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Backspace" && !digits[index] && index > 0) {
                refs.current[index - 1]?.focus();
                setFocused(index - 1);
              }
              if (e.key === "ArrowLeft" && index > 0) {
                e.preventDefault();
                refs.current[index - 1]?.focus();
              }
              if (e.key === "ArrowRight" && index < length - 1) {
                e.preventDefault();
                refs.current[index + 1]?.focus();
              }
            }}
            onPaste={(e) => {
              e.preventDefault();
              const pasted = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, length);
              if (!pasted) return;
              const next = Array.from({ length }, (_, i) => pasted[i] ?? "");
              emit(next);
              const focusAt = Math.min(pasted.length, length - 1);
              refs.current[focusAt]?.focus();
              setFocused(focusAt);
            }}
            className={cn(
              "h-11 w-10 rounded-lg border border-border bg-card text-center text-base font-medium tabular-nums text-foreground shadow-sm outline-none sm:h-12 sm:w-11",
              "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40",
              focused === index && "border-ring",
              ariaInvalid && "border-destructive",
              disabled && "opacity-60",
            )}
          />
        </span>
      ))}
    </div>
  );
}
