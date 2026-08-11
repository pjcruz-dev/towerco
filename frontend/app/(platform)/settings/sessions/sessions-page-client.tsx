"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { fetchSessions, logoutAll, revokeSession } from "@/lib/api/modules/auth-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  embedded?: boolean;
};

export function SessionsPageClient({ embedded = false }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const sessionsQuery = useQuery({
    queryKey: ["auth", "sessions"],
    queryFn: fetchSessions,
  });

  const revokeMutation = useMutation({
    mutationFn: revokeSession,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["auth", "sessions"] });
      push({ level: "success", title: "Session revoked" });
    },
  });

  const logoutAllMutation = useMutation({
    mutationFn: logoutAll,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["auth", "sessions"] });
      push({ level: "success", title: "All sessions revoked" });
    },
  });

  return (
    <div className={embedded ? "space-y-4" : "space-y-4"}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        {!embedded ? (
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Session Management</h1>
            <p className="text-sm text-muted-foreground">Track and revoke active devices/sessions.</p>
          </div>
        ) : (
          <div>
            <h2 className="text-base font-medium text-foreground">Active sessions</h2>
            <p className="text-sm text-muted-foreground">Devices signed in to your account.</p>
          </div>
        )}
        <Button
          variant="outline"
          disabled={logoutAllMutation.isPending}
          onClick={() => logoutAllMutation.mutate()}
        >
          Revoke all sessions
        </Button>
      </div>

      <div className="overflow-hidden rounded-xl border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left">
            <tr>
              <th className="px-4 py-3 font-medium">Device</th>
              <th className="px-4 py-3 font-medium">Auth</th>
              <th className="px-4 py-3 font-medium">State</th>
              <th className="px-4 py-3 font-medium">Last Seen</th>
              <th className="px-4 py-3 font-medium">MFA</th>
              <th className="px-4 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            {sessionsQuery.data?.map((session) => (
              <tr key={session.id} className="border-t">
                <td className="px-4 py-3 text-muted-foreground">{session.device_name ?? "Unknown"}</td>
                <td className="px-4 py-3">{session.auth_method}</td>
                <td className="px-4 py-3">{session.state}</td>
                <td className="px-4 py-3 text-muted-foreground">{session.last_seen_at ?? "-"}</td>
                <td className="px-4 py-3">{session.mfa_verified_at ? "Verified" : "Pending"}</td>
                <td className="px-4 py-3 text-right">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={revokeMutation.isPending}
                    onClick={() => revokeMutation.mutate(session.id)}
                  >
                    Revoke
                  </Button>
                </td>
              </tr>
            ))}
            {!sessionsQuery.data?.length ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                  No sessions found.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  );
}
