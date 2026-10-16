import { describe, expect, it } from "vitest";

import {
  CONTROLLED_REGISTER_PATH,
  controlledRegisterClearFilters,
  controlledRegisterFilterChips,
  controlledRegisterHasFilters,
  controlledRegisterHref,
  readControlledRegisterQuery,
} from "@/modules/documents/controlled-document-register-query";

const DEFAULT_SORT = "document_code:asc";

describe("controlled register query", () => {
  it("reads filters from the page URL", () => {
    const params = new URLSearchParams(
      "q=ATC&filter[department]=OMD&filter[status]=published&filter[review]=overdue&document=doc-1",
    );

    expect(readControlledRegisterQuery(params, DEFAULT_SORT)).toMatchObject({
      q: "ATC",
      sort: DEFAULT_SORT,
      department: "OMD",
      status: "published",
      review: "overdue",
      documentId: "doc-1",
    });
  });

  it("writes a shareable register link and drops the default sort", () => {
    const current = new URLSearchParams("document=doc-1");
    const href = controlledRegisterHref(
      current,
      { q: "leasing", sort: DEFAULT_SORT, "filter[file]": "missing" },
      DEFAULT_SORT,
    );

    expect(href.startsWith(`${CONTROLLED_REGISTER_PATH}?`)).toBe(true);
    const query = new URLSearchParams(href.split("?")[1]);
    expect(query.get("q")).toBe("leasing");
    expect(query.get("filter[file]")).toBe("missing");
    expect(query.get("document")).toBe("doc-1");
    expect(query.has("sort")).toBe(false);
  });

  it("builds removable chips and a clear-all patch", () => {
    const query = readControlledRegisterQuery(
      new URLSearchParams("q=policy&filter[effective_from]=2024-01-01&filter[file]=has"),
      DEFAULT_SORT,
    );

    expect(controlledRegisterHasFilters(query)).toBe(true);
    const chips = controlledRegisterFilterChips(query);
    expect(chips.map((chip) => chip.id)).toEqual(["q", "file", "effective"]);
    expect(chips[2]?.label).toBe("Effective 2024-01-01 – …");
    expect(controlledRegisterClearFilters()).toMatchObject({ q: null, "filter[file]": null });
  });
});