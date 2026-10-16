"use client";

import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import type { StatusTone } from "@/components/ui/status-tone";
import {
  authMethodLabel,
  mfaStatusLabel,
  resolveMfaDisplayStatus,
  type MfaDisplayStatus,
} from "@/lib/admin/user-display";

export function UserAuthMethodBadge({ method }: { method: string }) {
  return (
    <Badge variant="outline" className="font-normal">
      {authMethodLabel(method)}
    </Badge>
  );
}

export function UserAuthMethodsBadges({ methods }: { methods: string[] }) {
  if (methods.length === 0) {
    return <span className="text-xs text-muted-foreground">No sign-ins</span>;
  }

  return (
    <div className="flex flex-wrap gap-1">
      {methods.map((method) => (
        <UserAuthMethodBadge key={method} method={method} />
      ))}
    </div>
  );
}

const MFA_TONE: Record<MfaDisplayStatus, StatusTone> = {
  enrolled: "success",
  required_not_enrolled: "warning",
  not_required: "neutral",
};

export function UserMfaStatusBadge({
  mfaEnrolled,
  mfaRequired,
}: {
  mfaEnrolled: boolean;
  mfaRequired: boolean;
}) {
  const status = resolveMfaDisplayStatus(mfaEnrolled, mfaRequired);

  return (
    <StatusBadge tone={MFA_TONE[status]} className="font-normal">
      {mfaStatusLabel(status)}
    </StatusBadge>
  );
}
