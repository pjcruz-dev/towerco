import { redirect } from "next/navigation";

/** Legacy URL — `/create` is the canonical new-form route (avoids stale Next cache issues with `/new`). */
export default function EApprovalFormNewRedirectPage() {
  redirect("/e-approval/forms/create");
}
