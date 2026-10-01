"use client";

import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";

import {
  bundledRelease,
  parseProductRelease,
  PRODUCT_RELEASE_NOTES_KEY,
  PRODUCT_RELEASE_SEEN_KEY,
  type ProductRelease,
} from "@/content/release-notes";
import {
  fetchUserUiPreference,
  fetchUserUiPreferenceBundle,
} from "@/lib/api/modules/user-ui-preferences-api";
import { resolveAppVersionLabel } from "@/lib/runtime/app-environment";
import { useAuthStore } from "@/stores/auth-store";

export function useProductRelease() {
  const userId = useAuthStore((state) => state.user?.id);

  const notesQuery = useQuery({
    queryKey: ["me", "ui-preference", PRODUCT_RELEASE_NOTES_KEY],
    queryFn: () => fetchUserUiPreferenceBundle(PRODUCT_RELEASE_NOTES_KEY),
    enabled: Boolean(userId),
    retry: false,
  });

  const seenQuery = useQuery({
    queryKey: ["me", "ui-preference", PRODUCT_RELEASE_SEEN_KEY],
    queryFn: () => fetchUserUiPreference(PRODUCT_RELEASE_SEEN_KEY),
    enabled: Boolean(userId),
    retry: false,
  });

  const published = useMemo(
    () => parseProductRelease(notesQuery.data?.shared?.release),
    [notesQuery.data],
  );
  const release: ProductRelease = published ?? bundledRelease;
  const seenVersion =
    typeof seenQuery.data?.version === "string" ? seenQuery.data.version : null;

  const ready = Boolean(userId) && notesQuery.isSuccess && seenQuery.isSuccess;

  return {
    release,
    published,
    seenVersion,
    displayedVersion: published?.version ?? resolveAppVersionLabel(),
    ready,
    unseen: ready && seenVersion !== release.version,
  };
}
