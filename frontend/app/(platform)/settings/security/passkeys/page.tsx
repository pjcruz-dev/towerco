import { redirect } from "next/navigation";

export default function SettingsSecurityPasskeysRedirectPage() {
  redirect("/account/security?tab=passkeys");
}
