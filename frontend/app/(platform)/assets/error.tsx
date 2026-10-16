"use client";

import { Button } from "@/components/ui/button";

export default function AssetsError({ reset }: { error: Error; reset: () => void }) {
  return (
    <div className="mx-auto flex max-w-lg flex-col gap-3 p-6">
      <h1 className="text-2xl font-semibold text-foreground">Assets could not be loaded</h1>
      <p className="text-sm text-muted-foreground">Refresh the page. If this keeps happening, the request id is in the server log.</p>
      <Button type="button" onClick={() => reset()}>
        Retry
      </Button>
    </div>
  );
}
