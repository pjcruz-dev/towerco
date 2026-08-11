import { PlatformGuard } from "@/components/layout/platform-guard";

import "./print.css";

export default function PrintRootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <PlatformGuard>
      <div className="min-h-screen bg-white text-slate-900">{children}</div>
    </PlatformGuard>
  );
}
