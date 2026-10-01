import axios from "axios";
import { describe, expect, it } from "vitest";

import {
  getErrorMessage,
  isApiTimeoutError,
  isForbiddenApiError,
  isNetworkApiError,
} from "@/lib/api/error";

function axiosError(partial: {
  code?: string;
  message?: string;
  status?: number;
  url?: string;
}): unknown {
  return new axios.AxiosError(
    partial.message ?? "Request failed",
    partial.code,
    { url: partial.url },
    undefined,
    partial.status !== undefined
      ? {
          status: partial.status,
          statusText: "Error",
          data: {},
          headers: {},
          config: { url: partial.url },
        }
      : undefined,
  );
}

describe("api error helpers", () => {
  it("classifies timeouts separately from forbidden", () => {
    const timeout = axiosError({ code: "ECONNABORTED", message: "timeout of 20000ms exceeded" });
    expect(isApiTimeoutError(timeout)).toBe(true);
    expect(isForbiddenApiError(timeout)).toBe(false);
    expect(getErrorMessage(timeout)).toMatch(/timed out/i);
  });

  it("classifies 403 as forbidden", () => {
    const forbidden = axiosError({ status: 403, message: "Request failed with status code 403" });
    expect(isForbiddenApiError(forbidden)).toBe(true);
    expect(isApiTimeoutError(forbidden)).toBe(false);
  });

  it("classifies network errors", () => {
    const network = axiosError({ code: "ERR_NETWORK", message: "Network Error" });
    expect(isNetworkApiError(network)).toBe(true);
  });

  it("does not tell a roles timeout to open Submissions", () => {
    const timeout = axiosError({
      code: "ECONNABORTED",
      message: "timeout of 20000ms exceeded",
      url: "/admin/roles",
    });
    expect(getErrorMessage(timeout)).toMatch(/roles took too long/i);
    expect(getErrorMessage(timeout)).not.toMatch(/submissions/i);
  });
});
