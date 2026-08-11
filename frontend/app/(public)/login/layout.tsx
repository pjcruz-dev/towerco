import { TenantAuthChrome } from "@/components/layout/tenant-auth-chrome";

export default function TenantLoginLayout({ children }: { children: React.ReactNode }) {
  return <TenantAuthChrome>{children}</TenantAuthChrome>;
}
