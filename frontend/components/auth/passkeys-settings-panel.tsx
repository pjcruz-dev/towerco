"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Fingerprint, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchWebAuthnCredentials,
  revokeWebAuthnCredential,
  webAuthnRegisterOptions,
  webAuthnRegisterVerify,
} from "@/lib/api/modules/auth-api";
import {
  isPlatformAuthenticatorAvailable,
  isWebAuthnSupported,
  serializeAttestation,
  toCreationOptions,
  webAuthnUserMessage,
} from "@/lib/webauthn/browser";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  embedded?: boolean;
};

export function PasskeysSettingsPanel({ embedded = false }: Props) {
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const [label, setLabel] = useState("Work laptop");
  const [supported, setSupported] = useState<boolean | null>(null);
  const [platformOk, setPlatformOk] = useState<boolean | null>(null);

  useEffect(() => {
    setSupported(isWebAuthnSupported());
    void isPlatformAuthenticatorAvailable().then(setPlatformOk);
  }, []);

  const listQuery = useQuery({
    queryKey: ["auth", "webauthn", "credentials"],
    queryFn: fetchWebAuthnCredentials,
  });

  const enrollMutation = useMutation({
    mutationFn: async () => {
      if (!isWebAuthnSupported()) {
        throw new Error("This browser does not support passkeys.");
      }
      const options = await webAuthnRegisterOptions(label.trim() || undefined);
      const creation = toCreationOptions(options.publicKey);
      const credential = (await navigator.credentials.create({
        publicKey: creation,
      })) as PublicKeyCredential | null;
      if (!credential) {
        throw new Error("No passkey was created.");
      }
      return webAuthnRegisterVerify({
        challengeId: options.challenge_id,
        credential: serializeAttestation(credential),
        label: label.trim() || undefined,
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["auth", "webauthn", "credentials"] });
      const state = useAuthStore.getState();
      state.setSession({
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        sessionId: state.sessionId,
        mfaRequired: state.mfaRequired,
        mfaEnrollmentRequired: state.mfaEnrollmentRequired,
        passkeyEnrollmentRequired: false,
        mfaChallenge: state.mfaChallenge,
        user: state.user,
      });
      notify({
        level: "success",
        title: "Passkey added",
        message: "You can use fingerprint, Face ID, or Windows Hello on this device to sign in.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not add passkey",
        message: webAuthnUserMessage(error) || getErrorMessage(error),
      }),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: string) => revokeWebAuthnCredential(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["auth", "webauthn", "credentials"] });
      notify({ level: "success", title: "Passkey removed" });
    },
    onError: (error) =>
      notify({ level: "error", title: "Remove failed", message: getErrorMessage(error) }),
  });

  const rows = listQuery.data?.credentials ?? [];
  const orgEnabled = listQuery.data?.enabled !== false;
  const enrollmentRequired = listQuery.data?.enrollment_required === true;
  const policy = listQuery.data?.policy ?? "allow";
  const canEnroll = orgEnabled && supported !== false;

  return (
    <div className="space-y-6">
      {!embedded ? (
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Passkeys</h1>
          <p className="text-sm text-muted-foreground">
            Sign in with fingerprint, Face ID, or Windows Hello after you enroll a passkey on this
            device.
          </p>
        </div>
      ) : (
        <div>
          <h2 className="text-base font-medium text-foreground">Passkeys</h2>
          <p className="text-sm text-muted-foreground">
            Use fingerprint, Face ID, or Windows Hello on devices you trust. Enroll while signed in;
            each laptop or phone needs its own passkey.
          </p>
        </div>
      )}

      {enrollmentRequired ? (
        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
          Your organization requires a passkey before you can use the rest of the workspace. Add one
          below to continue.
        </p>
      ) : null}

      {!enrollmentRequired && orgEnabled && policy === "prefer" && rows.length === 0 ? (
        <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
          Your organization recommends enrolling a passkey for faster, phishing-resistant sign-in.
        </p>
      ) : null}

      {listQuery.isSuccess && !orgEnabled ? (
        <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
          Passkey sign-in is turned off for this organization. You can still remove existing passkeys.
          Ask an administrator to enable passkeys under Sign-in &amp; security.
        </p>
      ) : null}

      {supported === false ? (
        <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
          This browser does not support passkeys. Try Chrome, Edge, or Safari on a device with
          biometric unlock.
        </p>
      ) : null}

      {supported && platformOk === false ? (
        <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
          No platform authenticator was detected. You can still try adding a passkey if your OS
          supports Windows Hello, Touch ID, or a security key.
        </p>
      ) : null}

      <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="flex flex-wrap items-end gap-3">
          <div className="min-w-[200px] flex-1 space-y-1.5">
            <Label htmlFor="passkey-label">Device label</Label>
            <Input
              id="passkey-label"
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder="Work laptop"
              maxLength={120}
              disabled={!canEnroll || enrollMutation.isPending}
            />
          </div>
          <Button
            type="button"
            className="gap-1.5"
            disabled={!canEnroll || enrollMutation.isPending}
            onClick={() => enrollMutation.mutate()}
          >
            <Fingerprint className="h-4 w-4" aria-hidden />
            {enrollMutation.isPending ? "Waiting for device…" : "Add passkey"}
          </Button>
        </div>
        <p className="mt-2 text-xs text-muted-foreground">
          Your browser will prompt for fingerprint or PIN. Password and Microsoft sign-in remain
          available as backup.
        </p>
      </section>

      <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <h3 className="text-sm font-medium text-foreground">Registered passkeys</h3>
        {listQuery.isLoading ? (
          <p className="mt-3 text-sm text-muted-foreground">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="mt-3 text-sm text-muted-foreground">
            No passkeys yet. Add one on this device to enable fingerprint sign-in.
          </p>
        ) : (
          <ul className="mt-3 divide-y divide-border">
            {rows.map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <p className="text-sm font-medium text-foreground">{row.label ?? "Passkey"}</p>
                  <p className="text-xs text-muted-foreground">
                    {row.last_used_at
                      ? `Last used ${new Date(row.last_used_at).toLocaleString()}`
                      : row.created_at
                        ? `Added ${new Date(row.created_at).toLocaleString()}`
                        : "Registered"}
                  </p>
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1.5 text-destructive hover:text-destructive"
                  disabled={revokeMutation.isPending}
                  onClick={() => {
                    if (
                      window.confirm(
                        `Remove “${row.label ?? "Passkey"}”? You will need password or Microsoft to sign in on this device until you enroll again.`,
                      )
                    ) {
                      revokeMutation.mutate(row.id);
                    }
                  }}
                >
                  <Trash2 className="h-3.5 w-3.5" aria-hidden />
                  Remove
                </Button>
              </li>
            ))}
          </ul>
        )}
        {listQuery.data?.rp_id ? (
          <p className="mt-3 text-xs text-muted-foreground">
            Bound to organization host <span className="font-mono">{listQuery.data.rp_id}</span>
          </p>
        ) : null}
      </section>
    </div>
  );
}
