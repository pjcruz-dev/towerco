"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { EmailNotificationPolicyEditor } from "@/components/rollout/email-notification-policy-editor";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { patchRolloutPlaybookConfig } from "@/lib/api/modules/rollout-api";
import {
  normalizeEmailNotificationPolicies,
  type EmailNotificationPolicies,
} from "@/lib/rollout/email-notification-policies";
import type { RolloutPlaybookStatus } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  status: RolloutPlaybookStatus | undefined;
  canConfigure: boolean;
};

export function RolloutPlaybookEmailNotifications({ status, canConfigure }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const merged = normalizeEmailNotificationPolicies(status?.email_notification_policies);
  const [draft, setDraft] = useState<EmailNotificationPolicies>(merged);

  useEffect(() => {
    setDraft(normalizeEmailNotificationPolicies(status?.email_notification_policies));
  }, [status?.email_notification_policies]);

  const mutation = useMutation({
    mutationFn: () =>
      patchRolloutPlaybookConfig({
        email_notification_policies: draft,
      }),
    onSuccess: (data) => {
      queryClient.setQueryData(["project-one", "rollout-playbook"], data);
      void queryClient.invalidateQueries({ queryKey: ["project-one", "rollout-playbook"] });
      push({
        level: "success",
        title: "Email notifications saved",
        message: "Gate approval email rules updated for this tenant.",
      });
    },
    onError: (error) =>
      push({
        level: "error",
        title: "Could not save email notifications",
        message: getErrorMessage(error),
      }),
  });

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-base font-medium text-foreground">Email notifications</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Control who receives email when gate approvals are submitted, approved, rejected, or escalated.
            Platform policy bundle defaults apply until you save overrides here.
          </p>
        </div>
        {canConfigure ? (
          <Button
            size="sm"
            disabled={mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending ? "Saving…" : "Save notifications"}
          </Button>
        ) : null}
      </div>

      <div className="mt-4">
        <EmailNotificationPolicyEditor
          value={draft}
          onChange={setDraft}
          disabled={!canConfigure || mutation.isPending}
        />
      </div>
    </div>
  );
}
