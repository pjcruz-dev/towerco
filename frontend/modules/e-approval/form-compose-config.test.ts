import { describe, expect, it } from "vitest";

import {
  formComposeReadiness,
  mergeFormComposeIntoMetadata,
  parseFormComposeConfig,
  shouldUseSteppedCompose,
} from "@/modules/e-approval/form-compose-config";

function makeField(name: string, type: string, label?: string) {
  return { name, type, label: label ?? name, step_order: 1 };
}

describe("form compose config", () => {
  it("parses stepped compose metadata", () => {
    const config = parseFormComposeConfig({
      compose: {
        mode: "stepped",
        step_source: "page_breaks",
        show_progress: true,
        validate_on_next: false,
        allow_back: true,
        include_review_step: true,
      },
    });

    expect(config.mode).toBe("stepped");
    expect(config.stepSource).toBe("page_breaks");
    expect(config.validateOnNext).toBe(false);
    expect(config.allowBack).toBe(true);
    expect(config.includeReviewStep).toBe(true);
  });

  it("merges editor settings into metadata", () => {
    const merged = mergeFormComposeIntoMetadata({}, {
      mode: "stepped",
      stepSource: "auto",
      showProgress: true,
      validateOnNext: true,
      allowBack: true,
      includeReviewStep: true,
    });

    expect(merged.compose).toMatchObject({
      mode: "stepped",
      step_source: "auto",
      include_review_step: true,
    });
  });

  it("requires at least two section steps for stepped mode", () => {
    const fields = [
      makeField("section_a", "section", "Only"),
      makeField("title", "text"),
    ];
    const readiness = formComposeReadiness({
      mode: "stepped",
      stepSource: "sections",
      showProgress: true,
      validateOnNext: true,
      allowBack: true,
      includeReviewStep: false,
    }, fields);
    expect(readiness.ready).toBe(false);

    const config = parseFormComposeConfig({ compose: { mode: "stepped" } });
    expect(shouldUseSteppedCompose(config, fields)).toBe(false);
  });

  it("requires at least two page-break steps when using page breaks", () => {
    const fields = [
      makeField("title", "text"),
      makeField("break_1", "page_break", "More"),
    ];
    const readiness = formComposeReadiness({
      mode: "stepped",
      stepSource: "page_breaks",
      showProgress: true,
      validateOnNext: true,
      allowBack: true,
      includeReviewStep: false,
    }, fields);

    expect(readiness.ready).toBe(false);
    expect(readiness.message).toContain("Page break");
  });
});
