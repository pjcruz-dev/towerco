"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { getErrorMessage } from "@/lib/api/error";
import {
  completeEApprovalPublicationTraining,
  fetchEApprovalAssignableUsers,
  inviteEApprovalPublicationTraining,
  removeEApprovalPublicationTrainingInvite,
} from "@/lib/api/modules/e-approval-api";
import type { EApprovalSubmissionDetail } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";

type Training = NonNullable<EApprovalSubmissionDetail["publication_training"]>;

type Props = {
  submissionId: string;
  training: Training;
};

export function EApprovalPublicationTrainingCard({ submissionId, training }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [search, setSearch] = useState("");
  const people = useQuery({
    queryKey: ["e-approval", "assignable-users", "recipients"],
    queryFn: () => fetchEApprovalAssignableUsers("recipients"),
    enabled: training.can_invite,
    staleTime: 60_000,
  });

  const refresh = async (): Promise<void> => {
    await queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
    await queryClient.invalidateQueries({ queryKey: ["e-approval", "submissions"] });
    await queryClient.invalidateQueries({ queryKey: ["controlled-documents"] });
    await queryClient.invalidateQueries({ queryKey: ["documents", "controlled"] });
  };

  const invite = useMutation({
    mutationFn: (userId: string) => inviteEApprovalPublicationTraining(submissionId, [userId]),
    onSuccess: async () => {
      setSearch("");
      push({ level: "success", title: "Invitation sent" });
      await refresh();
    },
    onError: (error) => {
      push({ level: "error", title: "Could not invite", message: getErrorMessage(error) });
    },
  });

  const complete = useMutation({
    mutationFn: (inviteId?: string) => completeEApprovalPublicationTraining(submissionId, inviteId),
    onSuccess: async (next) => {
      const published = !next?.holding;
      push({
        level: "success",
        title: published ? "Document published" : "Training complete",
        message: published
          ? "Everyone finished. The published date is today, and the effective date is on or after that day."
          : "Waiting for the remaining people.",
      });
      await refresh();
    },
    onError: (error) => {
      push({ level: "error", title: "Could not record training", message: getErrorMessage(error) });
    },
  });

  const remove = useMutation({
    mutationFn: (inviteId: string) => removeEApprovalPublicationTrainingInvite(submissionId, inviteId),
    onSuccess: async (next) => {
      push({
        level: "success",
        title: next?.holding ? "Person removed" : "Document published",
      });
      await refresh();
    },
    onError: (error) => {
      push({ level: "error", title: "Could not update the list", message: getErrorMessage(error) });
    },
  });

  const invitedIds = useMemo(() => new Set(training.invites.map((row) => row.user_id)), [training.invites]);
  const query = search.trim().toLowerCase();
  const matches = (people.data ?? [])
    .filter((person) => !invitedIds.has(person.id))
    .filter((person) => {
      if (query.length < 1) return false;
      return person.name.toLowerCase().includes(query) || person.email.toLowerCase().includes(query);
    })
    .slice(0, 8);

  const total = training.total;
  const completed = training.completed;
  const percent = total === 0 ? 0 : Math.round((completed / total) * 100);
  const mine = training.invites.find((row) => row.can_complete);
  const busy = invite.isPending || complete.isPending || remove.isPending;

  return (
    <EApprovalSectionCard
      title="Mandatory training"
      description="This document publishes after every invited person finishes. The published date is that day. If the effective date is already past, it moves to the published date."
      actions={
        mine ? (
          <Button size="sm" disabled={busy} onClick={() => complete.mutate(undefined)}>
            {complete.isPending ? "Saving…" : "Training complete"}
          </Button>
        ) : null
      }
      dataHelp="ea-publication-training"
    >
      <div className="space-y-4">
        <div>
          <div className="flex items-center justify-between gap-3 text-sm">
            <p className="font-medium text-foreground">
              {total === 0 ? "No one invited yet" : `${completed} of ${total} complete`}
            </p>
            <p className="text-muted-foreground">{total === 0 ? "Publication closed" : `${percent}%`}</p>
          </div>
          <div
            className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            aria-label="Training completion"
          >
            <div className="h-full rounded-full bg-primary transition-[width]" style={{ width: `${percent}%` }} />
          </div>
        </div>

        {total === 0 ? (
          <p className="text-sm text-muted-foreground">
            Invite the people who must take this training. Publication stays closed until they finish.
          </p>
        ) : (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {training.invites.map((row) => (
              <li key={row.id} className="flex items-center justify-between gap-3 px-3 py-2">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-foreground">{row.name || "Person"}</p>
                  <p className="truncate text-xs text-muted-foreground">{row.email}</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <span className={row.status === "completed" ? "text-xs font-medium text-foreground" : "text-xs text-muted-foreground"}>
                    {row.status === "completed" ? "Done" : "Waiting"}
                  </span>
                  {row.can_mark ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={() => complete.mutate(row.id)}
                    >
                      Mark complete
                    </Button>
                  ) : null}
                  {row.can_remove ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={busy}
                      onClick={() => remove.mutate(row.id)}
                    >
                      Remove
                    </Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}

        {training.can_invite ? (
          <div className="space-y-2">
            <label htmlFor="training-person-search" className="text-sm font-medium text-foreground">
              Invite a person
            </label>
            <Input
              id="training-person-search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by name or email"
              disabled={busy}
              autoComplete="off"
            />
            {query.length > 0 && matches.length === 0 ? (
              <p className="text-sm text-muted-foreground">No matching person.</p>
            ) : null}
            {matches.length > 0 ? (
              <ul className="overflow-hidden rounded-lg border border-border">
                {matches.map((person) => (
                  <li key={person.id}>
                    <button
                      type="button"
                      className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                      disabled={busy}
                      onClick={() => invite.mutate(person.id)}
                    >
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium text-foreground">{person.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">{person.email}</span>
                      </span>
                      <span className="shrink-0 text-xs font-medium text-foreground">Add</span>
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}
