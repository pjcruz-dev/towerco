"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useState } from "react";

import { OtpauthQrCode } from "@/components/auth/otpauth-qr-code";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getErrorMessage } from "@/lib/api/error";
import { centralApiClient } from "@/lib/api/central-client";
import { useNotificationStore } from "@/stores/notification-store";

async function fetchMfaStatus(): Promise<{
  platform_mfa_required: boolean;
  platform_mfa_enrolled: boolean;
}> {
  const response = await centralApiClient.get<{
    data: { platform_mfa_required: boolean; platform_mfa_enrolled: boolean };
  }>("/platform/mfa/status");
  return response.data.data;
}

export function PlatformMfaSettingsPageClient() {
  const notify = useNotificationStore((s) => s.push);
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

  const statusQuery = useQuery({
    queryKey: ["platform", "mfa", "status"],
    queryFn: fetchMfaStatus,
  });

  const enrollStartMutation = useMutation({
    mutationFn: async () => {
      const response = await centralApiClient.post<{ data: { secret: string; otpauth_uri: string } }>(
        "/platform/mfa/enroll/start",
      );
      return response.data.data;
    },
    onSuccess: (data) => setSetup(data),
    onError: (error) =>
      notify({ level: "error", title: "Enrollment failed", message: getErrorMessage(error) }),
  });

  const enrollCompleteMutation = useMutation({
    mutationFn: async () => {
      const response = await centralApiClient.post<{ data: { recovery_codes: string[] } }>(
        "/platform/mfa/enroll/complete",
        { code },
      );
      return response.data.data;
    },
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes ?? []);
      setSetup(null);
      void statusQuery.refetch();
      notify({ level: "success", title: "MFA enrolled", message: "Authenticator app is now active." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Enrollment failed", message: getErrorMessage(error) }),
  });

  const regenerateMutation = useMutation({
    mutationFn: async () => {
      const response = await centralApiClient.post<{ data: { recovery_codes: string[] } }>(
        "/platform/mfa/recovery-codes/regenerate",
      );
      return response.data.data;
    },
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes ?? []);
      notify({ level: "success", title: "Recovery codes regenerated", message: "Store the new codes securely." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not regenerate codes", message: getErrorMessage(error) }),
  });

  const status = statusQuery.data;

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold text-foreground">MFA security</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Protect platform operator accounts with TOTP authenticator apps.
        </p>
      </header>

      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Status</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            Policy:{" "}
            <span className="font-medium text-foreground">
              {status?.platform_mfa_required ? "Required" : "Optional"}
            </span>
          </p>
          <p>
            Enrollment:{" "}
            <span className="font-medium text-foreground">
              {status?.platform_mfa_enrolled ? "Active" : "Not enrolled"}
            </span>
          </p>
        </CardContent>
      </Card>

      {!status?.platform_mfa_enrolled ? (
        <Card className="rounded-xl shadow-sm">
          <CardHeader>
            <CardTitle className="text-base font-medium">Enroll authenticator</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!setup ? (
              <Button type="button" onClick={() => enrollStartMutation.mutate()} disabled={enrollStartMutation.isPending}>
                Start enrollment
              </Button>
            ) : (
              <>
                <OtpauthQrCode otpauthUri={setup.otpauth_uri} />
                <details className="rounded-lg border border-border bg-muted/20 px-3 py-2">
                  <summary className="cursor-pointer text-sm font-medium text-foreground">
                    Can&apos;t scan? Enter key manually
                  </summary>
                  <p className="mt-2 break-all font-mono text-xs text-muted-foreground">{setup.secret}</p>
                </details>
                <FormInput
                  label="Verification code"
                  inputMode="numeric"
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
                <Button
                  type="button"
                  disabled={code.length !== 6 || enrollCompleteMutation.isPending}
                  onClick={() => enrollCompleteMutation.mutate()}
                >
                  Complete enrollment
                </Button>
              </>
            )}
          </CardContent>
        </Card>
      ) : (
        <Card className="rounded-xl shadow-sm">
          <CardHeader>
            <CardTitle className="text-base font-medium">Recovery codes</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Button
              type="button"
              variant="outline"
              disabled={regenerateMutation.isPending}
              onClick={() => regenerateMutation.mutate()}
            >
              Regenerate recovery codes
            </Button>
          </CardContent>
        </Card>
      )}

      {recoveryCodes.length > 0 ? (
        <Card className="rounded-xl shadow-sm">
          <CardHeader>
            <CardTitle className="text-base font-medium">Save these codes</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-1 font-mono text-xs">
              {recoveryCodes.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
