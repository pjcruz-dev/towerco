import { redirect } from "next/navigation";

/** Legacy path — notifications live in the header bell. */
export default function EApprovalNotificationsRedirectPage() {
  redirect("/dashboard");
}
