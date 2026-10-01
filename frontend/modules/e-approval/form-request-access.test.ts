import { describe, expect, it } from "vitest";

import {
  formRequestAccessFromMetadata,
  viewerCanStartFormRequest,
} from "@/modules/e-approval/form-request-access";

const allowed = "019e9044-f543-72bd-9b55-d9cb0c62c46c";

describe("form request access", () => {
  it("defaults to everyone", () => {
    expect(formRequestAccessFromMetadata(null)).toEqual({ mode: "all", userIds: [] });
    expect(viewerCanStartFormRequest({}, "anyone")).toBe(true);
  });

  it("hides the form from users who are not selected", () => {
    const metadata = {
      request_access: { mode: "selected", user_ids: [allowed] },
    };

    expect(viewerCanStartFormRequest(metadata, allowed)).toBe(true);
    expect(viewerCanStartFormRequest(metadata, "019e9044-f543-72bd-9b55-d9cb0c62c46d")).toBe(false);
  });
});
