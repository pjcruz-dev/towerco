"use client";

import type { ReactNode } from "react";

import { AppFooter } from "@/components/layout/app-footer";
import { PlatformConsoleHeader } from "@/components/layout/platform-console-header";
import { PlatformConsoleSidebar } from "@/components/layout/platform-console-sidebar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";

export function PlatformConsoleShell({ children }: { children: ReactNode }) {
  return (
    <SidebarProvider>
      <div className="flex h-screen w-full overflow-hidden bg-background text-foreground antialiased">
        <PlatformConsoleSidebar />
        <SidebarInset className="flex flex-1 flex-col overflow-hidden bg-transparent">
          <PlatformConsoleHeader />
            <main className="scrollbar-hide flex-1 overflow-y-auto p-6 lg:p-8">
              <div className="mx-auto max-w-[min(100%,1920px)]">{children}</div>
            </main>
          <AppFooter />
        </SidebarInset>
      </div>
    </SidebarProvider>
  );
}
