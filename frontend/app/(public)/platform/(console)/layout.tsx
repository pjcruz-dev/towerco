import { PlatformConsoleGuard } from "@/components/layout/platform-console-guard";
import { PlatformConsoleShell } from "@/components/layout/platform-console-shell";

export default function PlatformConsoleLayout({ children }: { children: React.ReactNode }) {
  return (
    <PlatformConsoleShell>
      <PlatformConsoleGuard>{children}</PlatformConsoleGuard>
    </PlatformConsoleShell>
  );
}
