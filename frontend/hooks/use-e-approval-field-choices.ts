"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchEApprovalMasterData } from "@/lib/api/modules/e-approval-api";
import {
  getMasterDataLookupKey,
  parseSelectChoices,
  type SelectChoice,
} from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export function useEApprovalFieldChoices(
  field: EApprovalFormFieldInput,
  enabled = true,
  allowRemoteMasterData = true,
): { choices: SelectChoice[]; isLoading: boolean; isError: boolean } {
  const masterKey = getMasterDataLookupKey(field);
  const staticChoices = parseSelectChoices(field);
  const shouldFetchRemote =
    enabled && allowRemoteMasterData && Boolean(masterKey) && staticChoices.length === 0;

  const masterQuery = useQuery({
    queryKey: ["e-approval", "master-data", masterKey],
    queryFn: () => fetchEApprovalMasterData(masterKey!),
    enabled: shouldFetchRemote,
    staleTime: 120_000,
  });

  if (!masterKey || !allowRemoteMasterData || staticChoices.length > 0) {
    return { choices: staticChoices, isLoading: false, isError: false };
  }

  const choices: SelectChoice[] =
    masterQuery.data?.options?.map((row) => ({
      value: String(row.value ?? row.code ?? row.label),
      label: String(row.label),
      subtitle: row.subtitle ? String(row.subtitle) : null,
    })) ?? [];

  return {
    choices: choices.length > 0 ? choices : staticChoices,
    isLoading: masterQuery.isLoading,
    isError: masterQuery.isError,
  };
}
