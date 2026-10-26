import { describe, expect, it } from "vitest";

import {
  environmentCloneCanCopyAgain,
  environmentCloneCopyAgainLabel,
} from "@/components/platform/environment-clone-progress";
import type { EnvironmentCloneSnapshot } from "@/lib/api/modules/platform-api";

function clone(overrides: Partial<EnvironmentCloneSnapshot> = {}): EnvironmentCloneSnapshot {
  return {
    id: "clone-1",
    status: "ready",
    source_tenant_id: "source",
    target_tenant_id: "target",
    pause_source: false,
    snapshot_at: "2026-10-02T01:20:02+00:00",
    files_copied: 897,
    files_total: 897,
    paused_schedules: 0,
    counts: null,
    error_message: null,
    ...overrides,
  };
}

describe("environment copy again", () => {
  it("offers another copy once a snapshot is ready", () => {
    expect(environmentCloneCanCopyAgain(clone())).toBe(true);
    expect(environmentCloneCopyAgainLabel(clone({ source_environment: "staging" }))).toBe(
      "Copy again from staging",
    );
  });

  it("hides the action while a copy is running", () => {
    expect(environmentCloneCanCopyAgain(clone({ status: "copying_database" }))).toBe(false);
  });

  it("lets a stopped refresh be started again and keeps a failed first copy on retry", () => {
    expect(
      environmentCloneCanCopyAgain(clone({ status: "cancelled", preserve_target: true })),
    ).toBe(true);
    expect(
      environmentCloneCanCopyAgain(clone({ status: "failed", preserve_target: true })),
    ).toBe(true);
    expect(environmentCloneCanCopyAgain(clone({ status: "failed", preserve_target: false }))).toBe(
      false,
    );
  });
});
