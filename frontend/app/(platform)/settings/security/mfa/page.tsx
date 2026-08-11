import { redirect } from "next/navigation";

export default function LegacyMfaSettingsPage() {
  redirect("/account/security?tab=mfa");
}
