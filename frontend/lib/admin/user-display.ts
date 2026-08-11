export type AdminUserLastActiveFilter = "all" | "7d" | "30d" | "90d" | "never";

export type AdminUserMfaFilter = "all" | "enrolled" | "not_enrolled";

const AUTH_METHOD_LABELS: Record<string, string> = {
  local: "Password",
  azure_sso: "Microsoft",
};

export function authMethodLabel(method: string): string {
  const key = method.trim().toLowerCase();
  return AUTH_METHOD_LABELS[key] ?? method.replace(/_/g, " ");
}

export function formatAuthMethods(methods: string[] | undefined): string {
  if (!methods || methods.length === 0) {
    return "—";
  }

  return methods.map(authMethodLabel).join(", ");
}

export function formatLastActive(value: string | null | undefined): string {
  if (!value) {
    return "Never";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  const now = Date.now();
  const diffMs = now - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60_000);

  if (diffMinutes < 1) {
    return "Just now";
  }
  if (diffMinutes < 60) {
    return `${diffMinutes}m ago`;
  }

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) {
    return `${diffHours}h ago`;
  }

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 30) {
    return `${diffDays}d ago`;
  }

  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

export function formatTimestamp(value: string | null | undefined): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return date.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export type MfaDisplayStatus = "not_required" | "enrolled" | "required_not_enrolled";

export function resolveMfaDisplayStatus(
  mfaEnrolled: boolean,
  mfaRequired: boolean,
): MfaDisplayStatus {
  if (mfaEnrolled) {
    return "enrolled";
  }

  return mfaRequired ? "required_not_enrolled" : "not_required";
}

export function mfaStatusLabel(status: MfaDisplayStatus): string {
  switch (status) {
    case "enrolled":
      return "MFA enrolled";
    case "required_not_enrolled":
      return "MFA required";
    default:
      return "MFA not enrolled";
  }
}
