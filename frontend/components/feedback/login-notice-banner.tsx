"use client";

import { AlertCircle, AlertTriangle, CheckCircle2, Info } from "lucide-react";

import type { LoginNotice } from "@/lib/auth/login-notice";

const styles: Record<
  LoginNotice["level"],
  { container: string; icon: typeof AlertCircle }
> = {
  error: {
    container:
      "border-red-300 bg-red-50 text-red-950 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-100",
    icon: AlertCircle,
  },
  warning: {
    container:
      "border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-100",
    icon: AlertTriangle,
  },
  info: {
    container:
      "border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-100",
    icon: Info,
  },
  success: {
    container:
      "border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-100",
    icon: CheckCircle2,
  },
};

type Props = {
  notice: LoginNotice;
  onDismiss?: () => void;
};

export function LoginNoticeBanner({ notice, onDismiss }: Props) {
  const config = styles[notice.level];
  const Icon = config.icon;

  return (
    <div
      className={`flex gap-3 rounded-lg border px-4 py-3 text-sm shadow-sm ${config.container}`}
      role="alert"
      aria-live="assertive"
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
      <div className="min-w-0 flex-1">
        <p className="font-medium">{notice.title}</p>
        {notice.message ? (
          <p className="mt-1 text-xs leading-relaxed opacity-90">{notice.message}</p>
        ) : null}
      </div>
      {onDismiss ? (
        <button
          type="button"
          className="shrink-0 text-xs font-medium underline underline-offset-2 opacity-80 hover:opacity-100"
          onClick={onDismiss}
        >
          Dismiss
        </button>
      ) : null}
    </div>
  );
}
