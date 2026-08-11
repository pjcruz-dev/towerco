import { redirect } from "next/navigation";

export default function LegacySessionsPage() {
  redirect("/account/security?tab=sessions");
}
