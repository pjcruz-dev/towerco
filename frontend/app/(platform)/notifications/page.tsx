import { redirect } from "next/navigation";

/** Notifications live in the header bell — no dedicated page. */
export default function TenantNotificationsPage() {
  redirect("/dashboard");
}
