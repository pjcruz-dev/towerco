"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchProjectOneProject } from "@/lib/api/modules/project-one-api";

export function useProjectOneProjectDetail(projectId: string) {
  const enabled = Boolean(projectId) && projectId !== "undefined" && projectId !== "null";

  return useQuery({
    queryKey: ["project-one", "projects", "detail", projectId],
    queryFn: () => fetchProjectOneProject(projectId),
    enabled,
  });
}
