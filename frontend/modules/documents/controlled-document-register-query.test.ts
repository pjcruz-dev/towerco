import { describe, expect, it } from "vitest";

import {
  CONTROLLED_REGISTER_PATH,
  controlledRegisterClearFilters,
  controlledRegisterCountLabel,
  controlledRegisterFilterChips,
  controlledRegisterHasFilters,
  controlledRegisterHref,
  readControlledRegisterPage,
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

  it("keeps the page in the URL and drops it when the list changes", () => {
    expect(readControlledRegisterPage("3")).toBe(3);
    expect(readControlledRegisterPage("0")).toBe(1);
    expect(readControlledRegisterPage("nope")).toBe(1);
    expect(readControlledRegisterQuery(new URLSearchParams("page=4&q=ATC"), DEFAULT_SORT).page).toBe(4);

    const paged = new URLSearchParams("page=4&q=ATC&document=doc-1");
    const nextPage = controlledRegisterHref(paged, { page: "2" }, DEFAULT_SORT);
    expect(new URLSearchParams(nextPage.split("?")[1]).get("page")).toBe("2");
    expect(new URLSearchParams(nextPage.split("?")[1]).get("document")).toBe("doc-1");

    const opened = controlledRegisterHref(paged, { document: "doc-2" }, DEFAULT_SORT);
    expect(new URLSearchParams(opened.split("?")[1]).get("page")).toBe("4");

    const filtered = controlledRegisterHref(paged, { "filter[status]": "obsolete" }, DEFAULT_SORT);
    expect(new URLSearchParams(filtered.split("?")[1]).has("page")).toBe(false);

    const first = controlledRegisterHref(paged, { page: "1" }, DEFAULT_SORT);
    expect(new URLSearchParams(first.split("?")[1]).has("page")).toBe(false);
  });

  it("names the visible window when the register has more than one page", () => {
    expect(controlledRegisterCountLabel(undefined, true)).toBe("Loading documents");
    expect(
      controlledRegisterCountLabel(
        { current_page: 1, per_page: 50, total: 12, data: Array(12) },
        false,
      ),
    ).toBe("12 documents");
    expect(
      controlledRegisterCountLabel(
        { current_page: 1, per_page: 50, total: 1, data: Array(1) },
        false,
      ),
    ).toBe("1 document");
    expect(
      controlledRegisterCountLabel(
        { current_page: 2, per_page: 50, total: 180, data: Array(50) },
        false,
      ),
    ).toBe("Showing 51–100 of 180");
    expect(
      controlledRegisterCountLabel(
        { current_page: 2, per_page: 50, total: 51, data: Array(1) },
        false,
      ),
    ).toBe("Showing 51 of 51");
  });
});