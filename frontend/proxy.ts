import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";

import { isAppMenuLauncherHostname, isCentralHostname } from "@/lib/tenant/resolve-tenant-domain";

/**
 * - Central host: /login → /platform/login
 * - App Menu launcher host (appmenu.*): / → /appmenu
 *
 * Do not match /platform here — Next.js 16 proxy matching those paths can 404
 * App Router pages under app/(public)/platform in dev. Tenant hosts are blocked
 * from /platform by app/(public)/platform/layout.tsx instead.
 */
export function proxy(request: NextRequest) {
  const host = request.headers.get("host")?.split(":")[0]?.toLowerCase() ?? "";
  const central = isCentralHostname(host);
  const launcher = isAppMenuLauncherHostname(host);
  const { pathname } = request.nextUrl;

  if (launcher && (pathname === "/" || pathname === "")) {
    return NextResponse.redirect(new URL("/appmenu", request.url));
  }

  if (central && (pathname === "/login" || pathname === "/login/")) {
    return NextResponse.redirect(new URL("/platform/login", request.url));
  }

  return NextResponse.next({ request });
}

export const config = {
  matcher: ["/", "/login", "/login/"],
};
