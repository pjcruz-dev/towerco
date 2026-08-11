"use client";

import { Badge } from "@/components/ui/badge";
import {
  authMethodLabel,
  mfaStatusLabel,
  resolveMfaDisplayStatus,
  type MfaDisplayStatus,
} from "@/lib/admin/user-display";
import { cn } from "@/lib/utils";

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

const MFA_BADGE_CLASS: Record<MfaDisplayStatus, string> = {
  enrolled: "border-success/30 bg-success/10 text-success",
  required_not_enrolled: "border-warning/30 bg-warning/10 text-warning",
  not_required: "text-muted-foreground",
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
    <Badge variant="secondary" className={cn("font-normal", MFA_BADGE_CLASS[status])}>
      {mfaStatusLabel(status)}
    </Badge>
  );
}
