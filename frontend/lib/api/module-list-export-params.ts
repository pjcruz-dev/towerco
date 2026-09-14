/**
 * Query serialization for module list exports.
 * Prefer `ids[]=` / `columns[]=` so PHP always receives arrays (even for one item).
 * Bare `ids=uuid` breaks Laravel `'ids' => ['array']` validation (422).
 */

import type { AxiosRequestConfig } from "axios";

/** Use on export GETs that send `ids` / `columns` / `statuses` arrays. */
export const moduleListExportParamsSerializer: NonNullable<
  AxiosRequestConfig["paramsSerializer"]
> = (params) => {
  const search = new URLSearchParams();
  for (const [key, raw] of Object.entries(params ?? {})) {
    if (raw === undefined || raw === null || raw === "") continue;
    if (Array.isArray(raw)) {
      if (raw.length === 0) continue;
      for (const item of raw) {
        if (item === undefined || item === null || item === "") continue;
        search.append(`${key}[]`, String(item));
      }
      continue;
    }
    search.append(key, String(raw));
  }
  return search.toString();
};
