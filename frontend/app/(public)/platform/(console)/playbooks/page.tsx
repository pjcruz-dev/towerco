"use client";

import Link from "next/link";
import { useEffect } from "react";
import { useRouter } from "next/navigation";

import { buttonVariants } from "@/components/ui/button";

/**
 * Rollout Playbooks were Project-One only (gates / SLA timelines).
 * That module is retired — Dynamic Entities packs replace PM / Procurement / Finance workflows.
 */
export default function PlatformPlaybooksPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace("/platform");
  }, [router]);

  return (
    <div className="mx-auto max-w-lg space-y-4 py-16 text-center">
      <h1 className="text-2xl font-semibold text-foreground">Rollout playbooks retired</h1>
      <p className="text-sm text-muted-foreground">
        Project-One rollout playbooks are no longer used. Site / PM / Procurement / Finance work now
        runs through <span className="font-medium text-foreground">Dynamic Entities</span> packs.
      </p>
      <Link href="/platform" className={buttonVariants({ variant: "default" })}>
        Back to tenant directory
      </Link>
    </div>
  );
}
