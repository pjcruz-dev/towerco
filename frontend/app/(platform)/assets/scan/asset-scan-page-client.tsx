"use client";

import dynamic from "next/dynamic";
import { useRouter } from "next/navigation";
import { useCallback, useState } from "react";

import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { adminPageShellClass } from "@/lib/ui/page-shell";

const AssetQrScanner = dynamic(
  () => import("./asset-qr-scanner").then((mod) => mod.AssetQrScanner),
  { ssr: false },
);

export function AssetScanPageClient() {
  const router = useRouter();
  const [tag, setTag] = useState("");
  const openTag = useCallback(
    (value: string) => {
      const cleaned = value.trim().split("/").filter(Boolean).pop() ?? "";
      if (cleaned) {
        router.push(`/assets/${encodeURIComponent(cleaned)}`);
      }
    },
    [router],
  );

  return (
    <div className={`${adminPageShellClass} max-w-sm`}>
      <WorkspacePageHeader title="Scan asset" description="Point the camera at the tag, or type it." />
      <AssetQrScanner onScan={openTag} />
      <form
        className="space-y-3"
        onSubmit={(event) => {
          event.preventDefault();
          openTag(tag);
        }}
      >
        <Label htmlFor="manual-tag">Asset tag</Label>
        <Input id="manual-tag" value={tag} onChange={(event) => setTag(event.target.value)} className="h-11 font-mono" />
        <Button type="submit" className="h-11 w-full">
          Open asset
        </Button>
      </form>
    </div>
  );
}
