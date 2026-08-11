import { AppShell } from "@/components/layout/app-shell";
import { PlatformGuard } from "@/components/layout/platform-guard";

export default function PlatformLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <PlatformGuard>
      <AppShell>{children}</AppShell>
    </PlatformGuard>
  );
}
