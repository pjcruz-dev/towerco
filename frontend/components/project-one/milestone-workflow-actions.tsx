"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import { updateProjectMilestoneStatus } from "@/lib/api/modules/project-one-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type WorkflowStatus = "pending" | "in_progress" | "completed" | "overdue";

type Props = {
  milestoneId: string;
  status: string;
  invalidateKeys?: Array<Array<string | number>>;
};

export function MilestoneWorkflowActions({ milestoneId, status, invalidateKeys = [] }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const canManage = usePermission([permissions.projectOneManage]);
  const workflow = status as WorkflowStatus;

  const mutation = useMutation({
    mutationFn: (next: WorkflowStatus) => updateProjectMilestoneStatus(milestoneId, next),
    onSuccess: () => {
      for (const key of invalidateKeys) {
        void queryClient.invalidateQueries({ queryKey: key });
      }
      void queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      push({ level: "success", title: "Milestone updated" });
    },
    onError: (error) => {
      push({ level: "error", title: "Could not update milestone", message: getErrorMessage(error) });
    },
  });

  if (!canManage || workflow === "completed") {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-2">
      {workflow === "pending" || workflow === "overdue" ? (
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={mutation.isPending}
          onClick={() => mutation.mutate("in_progress")}
        >
          Start
        </Button>
      ) : null}
      {workflow === "in_progress" ? (
        <Button
          type="button"
          size="sm"
          disabled={mutation.isPending}
          onClick={() => mutation.mutate("completed")}
        >
          Mark complete
        </Button>
      ) : null}
    </div>
  );
}
