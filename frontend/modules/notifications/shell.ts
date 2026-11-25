import { resolveModuleShell } from "@/lib/navigation/module-shell";

export type NotificationShell = "edcs" | "e_approval" | "ticketing" | "asset_management";

/** The inbox follows the module that is open. The dashboard keeps every module. */
export function notificationShellForPath(pathname: string): NotificationShell | null {
  const shell = resolveModuleShell(pathname);
  if (shell.id === "document_register") {
    return "edcs";
  }
  if (shell.id === "e_approval" || shell.id === "ticketing" || shell.id === "asset_management") {
    return shell.id;
  }
  return null;
}
