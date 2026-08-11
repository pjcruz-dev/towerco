import { PlatformGuard } from "@/components/layout/platform-guard";

export default function RequestFocusLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <PlatformGuard>{children}</PlatformGuard>;
}
