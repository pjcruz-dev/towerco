"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { Info, Shield } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  fetchTenantMicrosoftSsoConfig,
  fetchTenantSecuritySettings,
  testTenantMicrosoftSsoConnection,
  updateTenantMicrosoftSsoConfig,
  updateTenantSecuritySettings,
} from "@/lib/api/modules/admin-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

function parseAllowedDomains(text: string): string[] {
  const seen = new Set<string>();
  const domains: string[] = [];

  for (const raw of text.split(/[\n,]+/)) {
    const domain = raw.trim().replace(/^@+/, "").toLowerCase();
    if (domain === "" || seen.has(domain)) {
      continue;
    }
    seen.add(domain);
    domains.push(domain);
  }

  return domains;
}

function formatAllowedDomains(domains: string[] | undefined): string {
  return (domains ?? []).join("\n");
}

/** Entra group → role map must be a JSON object; `[]` is treated as “no mappings”. */
function parseGroupMappingJson(text: string): Record<string, string[]> {
  const trimmed = text.trim();
  if (trimmed === "" || trimmed === "[]") {
    return {};
  }

  const parsed = JSON.parse(trimmed) as unknown;
  if (Array.isArray(parsed)) {
    if (parsed.length === 0) {
      return {};
    }
    throw new Error("Group mapping must be a JSON object. Use {} when you are not mapping Entra groups.");
  }

  if (parsed !== null && typeof parsed === "object") {
    return parsed as Record<string, string[]>;
  }

  throw new Error("Group mapping must be a JSON object.");
}

function formatGroupMappingRules(rules: Record<string, string[]> | unknown): string {
  if (rules === null || rules === undefined) {
    return "{}";
  }
  if (Array.isArray(rules)) {
    return "{}";
  }
  if (typeof rules === "object" && Object.keys(rules as object).length === 0) {
    return "{}";
  }
  return JSON.stringify(rules, null, 2);
}

export function TenantSettingsPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);

  const [clientId, setClientId] = useState("");
  const [clientSecret, setClientSecret] = useState("");
  const [tenantIdentifier, setTenantIdentifier] = useState("common");
  const [issuer, setIssuer] = useState("");
  const [enabled, setEnabled] = useState(false);
  const [autoProvision, setAutoProvision] = useState(false);
  const [disablePasswordWhenSso, setDisablePasswordWhenSso] = useState(true);
  const [allowedDomainsText, setAllowedDomainsText] = useState("");
  const [groupMappingJson, setGroupMappingJson] = useState("{}");
  const [redirectUri, setRedirectUri] = useState("");
  const [mfaRequired, setMfaRequired] = useState(false);
  const [mfaTrustDays, setMfaTrustDays] = useState(7);
  const [passkeysEnabled, setPasskeysEnabled] = useState(true);
  const [passkeysPolicy, setPasskeysPolicy] = useState<"allow" | "prefer" | "require">("allow");
  const [passkeysSatisfiesMfa, setPasskeysSatisfiesMfa] = useState(true);

  const configQuery = useQuery({
    queryKey: ["admin", "sso", "microsoft"],
    queryFn: fetchTenantMicrosoftSsoConfig,
  });

  const securityQuery = useQuery({
    queryKey: ["admin", "security"],
    queryFn: fetchTenantSecuritySettings,
  });

  useEffect(() => {
    const config = configQuery.data;
    if (!config) {
      return;
    }

    setClientId(config.client_id ?? "");
    setTenantIdentifier(config.tenant_identifier ?? "common");
    setIssuer(config.issuer ?? "");
    setEnabled(config.enabled);
    setAutoProvision(config.auto_provision_users);
    setDisablePasswordWhenSso(config.disable_password_login_when_enabled ?? true);
    setAllowedDomainsText(formatAllowedDomains(config.allowed_email_domains));
    setRedirectUri(config.redirect_uri ?? "");
    setGroupMappingJson(formatGroupMappingRules(config.group_mapping_rules));
    setClientSecret("");
  }, [configQuery.data]);

  useEffect(() => {
    if (securityQuery.data) {
      setMfaRequired(securityQuery.data.mfa_required);
      setMfaTrustDays(securityQuery.data.mfa_trust_days ?? 7);
      setPasskeysEnabled(securityQuery.data.passkeys_enabled);
      setPasskeysPolicy(securityQuery.data.passkeys_policy ?? "allow");
      setPasskeysSatisfiesMfa(securityQuery.data.passkeys_satisfies_mfa ?? true);
    }
  }, [securityQuery.data]);

  const parsedAllowedDomains = useMemo(
    () => parseAllowedDomains(allowedDomainsText),
    [allowedDomainsText],
  );

  const saveMutation = useMutation({
    mutationFn: async () => {
      let groupMappingRules: Record<string, string[]>;
      try {
        groupMappingRules = parseGroupMappingJson(groupMappingJson);
      } catch (error) {
        throw error instanceof Error ? error : new Error("Invalid group mapping JSON.");
      }

      return updateTenantMicrosoftSsoConfig({
        client_id: clientId.trim(),
        client_secret: clientSecret.trim() || undefined,
        tenant_identifier: tenantIdentifier.trim() || "common",
        issuer: issuer.trim() || null,
        enabled,
        auto_provision_users: autoProvision,
        disable_password_login_when_enabled: disablePasswordWhenSso,
        allowed_email_domains: parsedAllowedDomains,
        group_mapping_rules: groupMappingRules,
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "sso", "microsoft"] });
      void queryClient.invalidateQueries({ queryKey: ["auth", "public", "status"] });
      setClientSecret("");
      push({ level: "success", title: "Sign-in settings saved" });
    },
    onError: (error) =>
      push({ level: "error", title: "Save failed", message: getErrorMessage(error) }),
  });

  const securitySaveMutation = useMutation({
    mutationFn: () =>
      updateTenantSecuritySettings({
        mfa_required: mfaRequired,
        mfa_trust_days: mfaTrustDays,
        passkeys_enabled: passkeysEnabled,
        passkeys_policy: passkeysPolicy,
        passkeys_satisfies_mfa: passkeysSatisfiesMfa,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "security"] });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      void queryClient.invalidateQueries({ queryKey: ["auth", "public", "status"] });
      push({ level: "success", title: "Security settings saved" });
    },
    onError: (error) =>
      push({ level: "error", title: "Save failed", message: getErrorMessage(error) }),
  });

  const testMutation = useMutation({
    mutationFn: () =>
      testTenantMicrosoftSsoConnection({
        client_id: clientId.trim(),
        tenant_identifier: tenantIdentifier.trim(),
        client_secret: clientSecret.trim() || undefined,
      }),
    onSuccess: (result) => {
      if (result.redirect_uri) {
        setRedirectUri(result.redirect_uri);
      }
      push({
        level: result.ok ? "success" : "warning",
        title: result.ok ? "Settings look valid" : "Check configuration",
        message: result.message,
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Test failed", message: getErrorMessage(error) }),
  });

  const hasStoredSecret = configQuery.data?.has_client_secret ?? false;
  const hasConfig = configQuery.data !== null && configQuery.data !== undefined;
  const security = securityQuery.data;

  return (
    <PermissionGate requiredPermissions={[permissions.tenantManage]}>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Sign-in &amp; security</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Microsoft Entra ID, password sign-in policy, and optional email domain restrictions for this
            organization.
          </p>
        </div>

        {configQuery.isError ? (
          <p className="text-sm text-destructive">Could not load sign-in settings.</p>
        ) : null}

        <EApprovalSectionCard
          title="MFA enforcement"
          description="Require authenticator enrollment for all users in this organization."
        >
          <div className="mt-4 space-y-4">
            {securityQuery.isError ? (
              <p className="text-sm text-destructive">Could not load MFA policy.</p>
            ) : null}

            <div className="flex gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
              <Shield className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
              <p>
                MFA enforcement is active only when the platform master switch{" "}
                <code className="rounded bg-muted px-1 text-xs">TENANT_MFA_REQUIRED</code> is enabled and this
                organization policy is on. Users enroll under Settings → MFA security.
              </p>
            </div>

            <label className="flex items-start gap-2 text-sm">
              <Checkbox
                className="mt-1 size-4"
                checked={mfaRequired}
                onCheckedChange={(v) => setMfaRequired(v === true)}
                disabled={securityQuery.isLoading}
              />
              <span>
                <span className="font-medium text-foreground">Require MFA for all users</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  {security?.mfa_global_enabled
                    ? security.mfa_policy_active
                      ? "Policy is currently enforced for this organization."
                      : "Global MFA is on; save to enforce for this organization."
                    : "Global MFA master switch is off — this setting is stored but not enforced yet."}
                </span>
              </span>
            </label>

            <div className="space-y-1.5">
              <Label htmlFor="mfa-trust-days">Remember MFA on this browser (days)</Label>
              <Input
                id="mfa-trust-days"
                type="number"
                min={0}
                max={90}
                className="max-w-[8rem]"
                value={mfaTrustDays}
                onChange={(e) => {
                  const next = Number(e.target.value);
                  setMfaTrustDays(Number.isFinite(next) ? Math.max(0, Math.min(90, Math.trunc(next))) : 0);
                }}
                disabled={securityQuery.isLoading}
              />
              <p className="text-xs text-muted-foreground">
                After a successful authenticator check, skip MFA on the same browser for this many days.
                Use <span className="font-medium text-foreground">0</span> to require MFA on every sign-in.
                Maximum 90 days.
              </p>
            </div>

            <Button
              type="button"
              variant="outline"
              onClick={() => securitySaveMutation.mutate()}
              disabled={securitySaveMutation.isPending || securityQuery.isLoading}
            >
              {securitySaveMutation.isPending ? "Saving…" : "Save MFA policy"}
            </Button>
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Passkeys"
          description="Allow fingerprint, Face ID, or Windows Hello sign-in via WebAuthn passkeys."
        >
          <div className="mt-4 space-y-4">
            <div className="flex gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
              <Shield className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
              <p>
                Password and Microsoft sign-in remain available for recovery. Admins can revoke a
                user’s passkeys from Team &amp; Access → user → Activity. Platform kill switch:{" "}
                <code className="rounded bg-muted px-1 text-xs">TOWEROS_TENANT_PASSKEYS_ENABLED</code>.
              </p>
            </div>

            <label className="flex items-start gap-2 text-sm">
              <Checkbox
                className="mt-1 size-4"
                checked={passkeysEnabled}
                onCheckedChange={(v) => setPasskeysEnabled(v === true)}
                disabled={securityQuery.isLoading || security?.passkeys_global_enabled === false}
              />
              <span>
                <span className="font-medium text-foreground">Enable passkey sign-in</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  {security?.passkeys_global_enabled === false
                    ? "Platform master switch is off — passkeys cannot be enabled for this organization."
                    : "When on, users can enroll under My security → Passkeys and use Sign in with passkey on the login page."}
                </span>
              </span>
            </label>

            <div className="space-y-1.5">
              <Label htmlFor="passkeys-policy">Passkey policy</Label>
              <select
                id="passkeys-policy"
                className="flex h-10 w-full max-w-md rounded-md border border-input bg-background px-3 text-sm"
                value={passkeysPolicy}
                disabled={!passkeysEnabled || securityQuery.isLoading}
                onChange={(e) =>
                  setPasskeysPolicy(e.target.value as "allow" | "prefer" | "require")
                }
              >
                <option value="allow">Allow — optional</option>
                <option value="prefer">Prefer — recommend on login</option>
                <option value="require">Require — must enroll after sign-in</option>
              </select>
              <p className="text-xs text-muted-foreground">
                Require still allows password/Microsoft for recovery, then blocks the workspace until a
                passkey is enrolled (break-glass admins exempt).
              </p>
            </div>

            <label className="flex items-start gap-2 text-sm">
              <Checkbox
                className="mt-1 size-4"
                checked={passkeysSatisfiesMfa}
                onCheckedChange={(v) => setPasskeysSatisfiesMfa(v === true)}
                disabled={!passkeysEnabled || securityQuery.isLoading}
              />
              <span>
                <span className="font-medium text-foreground">
                  Passkey sign-in satisfies authenticator MFA
                </span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  When on, signing in with a passkey skips the TOTP challenge (platform authenticator
                  already verified the user). Password and Microsoft sign-in still follow MFA policy.
                </span>
              </span>
            </label>

            <Button
              type="button"
              variant="outline"
              onClick={() => securitySaveMutation.mutate()}
              disabled={
                securitySaveMutation.isPending ||
                securityQuery.isLoading ||
                security?.passkeys_global_enabled === false
              }
            >
              {securitySaveMutation.isPending ? "Saving…" : "Save passkey policy"}
            </Button>
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Sign-in security policies"
          description="Applies to password login and Microsoft sign-in for this organization. Entra ID remains the primary gate for Microsoft users."
        >
          <div className="mt-4 space-y-4">
            <div className="flex gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
              <p>
                Standard posture: invite users in Team &amp; Access, enable Microsoft sign-in, keep auto-provision
                off, and restrict password login when SSO is on. Break-glass bootstrap admins keep password access.
              </p>
            </div>

            <label className="flex items-start gap-2 text-sm">
              <Checkbox
                className="mt-1 size-4"
                checked={autoProvision}
                onCheckedChange={(v) => setAutoProvision(v === true)}
              />
              <span>
                <span className="font-medium text-foreground">Auto-provision on first Microsoft sign-in</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  When off, users must exist in Team &amp; Access before they can sign in with Microsoft.
                </span>
              </span>
            </label>

            <label className="flex items-start gap-2 text-sm">
              <Checkbox
                className="mt-1 size-4"
                checked={disablePasswordWhenSso}
                onCheckedChange={(v) => setDisablePasswordWhenSso(v === true)}
                disabled={!enabled}
              />
              <span>
                <span className="font-medium text-foreground">
                  Disable password sign-in when Microsoft sign-in is enabled
                </span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  Break-glass bootstrap administrators (e.g. admin@your-org-host) can still use password.
                </span>
              </span>
            </label>

            <div className="space-y-1.5">
              <Label htmlFor="allowed-domains">Allowed email domains (optional)</Label>
              <Textarea
                id="allowed-domains"
                className="min-h-[88px] font-mono text-xs"
                value={allowedDomainsText}
                onChange={(e) => setAllowedDomainsText(e.target.value)}
                placeholder={"atc.com\nalliancetowers.com"}
                spellCheck={false}
              />
              <p className="text-xs text-muted-foreground">
                One domain per line (or comma-separated). Leave empty to allow any email domain. Restricts
                Microsoft sign-in / auto-provision and normal password users. Break-glass bootstrap admins
                (password-exempt, e.g. admin@staging.myapp.localhost) are not blocked by this list.
              </p>
              {parsedAllowedDomains.length > 0 ? (
                <p className="text-xs text-muted-foreground">
                  Active:{" "}
                  {parsedAllowedDomains.map((d) => (
                    <code key={d} className="mr-1 rounded bg-muted px-1">
                      @{d}
                    </code>
                  ))}
                </p>
              ) : (
                <p className="text-xs text-muted-foreground">No domain restriction (all domains allowed).</p>
              )}
            </div>
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Microsoft Entra ID"
          description="Organization app registration (central database). Powers Sign in with Microsoft and Entra Graph for manager approval steps."
        >
          <div className="mt-4 space-y-4">
            <label className="flex items-center gap-2 text-sm">
              <Checkbox
                className="size-4"
                checked={enabled}
                onCheckedChange={(v) => setEnabled(v === true)}
              />
              Enable Microsoft sign-in for this organization
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5 sm:col-span-2">
                <Label htmlFor="ms-client-id">Application (client) ID</Label>
                <Input
                  id="ms-client-id"
                  value={clientId}
                  onChange={(e) => setClientId(e.target.value)}
                  placeholder="00000000-0000-0000-0000-000000000000"
                  autoComplete="off"
                />
              </div>

              <div className="space-y-1.5 sm:col-span-2">
                <Label htmlFor="ms-client-secret">Client secret</Label>
                <Input
                  id="ms-client-secret"
                  type="password"
                  value={clientSecret}
                  onChange={(e) => setClientSecret(e.target.value)}
                  placeholder={
                    hasStoredSecret
                      ? "Leave blank to keep existing secret"
                      : "Required when enabling sign-in"
                  }
                  autoComplete="new-password"
                />
                {hasStoredSecret ? (
                  <p className="text-xs text-muted-foreground">A secret is already stored for this organization.</p>
                ) : null}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="ms-tenant-id">Directory ID</Label>
                <Input
                  id="ms-tenant-id"
                  value={tenantIdentifier}
                  onChange={(e) => setTenantIdentifier(e.target.value)}
                  placeholder="common or your Entra directory GUID"
                />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="ms-issuer">Issuer (optional)</Label>
                <Input
                  id="ms-issuer"
                  value={issuer}
                  onChange={(e) => setIssuer(e.target.value)}
                  placeholder="https://login.microsoftonline.com/…/v2.0"
                />
              </div>
            </div>

            <div className="rounded-lg border border-border bg-muted/20 px-3 py-3 text-sm">
              <p className="flex items-center gap-2 font-medium">
                <Shield className="h-4 w-4 text-primary" aria-hidden />
                Azure redirect URI
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                Add this exact URI under Azure Portal → App registrations → Authentication → Web redirect URIs.
              </p>
              <code className="mt-2 block break-all rounded bg-background px-2 py-1 text-xs">
                {redirectUri || (hasConfig ? "—" : "Save settings to generate redirect URI")}
              </code>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="ms-group-map">Entra group → role mapping (JSON, optional)</Label>
              <Textarea
                id="ms-group-map"
                className="min-h-[120px] font-mono text-xs"
                value={groupMappingJson}
                onChange={(e) => setGroupMappingJson(e.target.value)}
                spellCheck={false}
              />
              <p className="text-xs text-muted-foreground">
                Example:{" "}
                <code className="rounded bg-muted px-1">{`{"<entra-group-id>": ["viewer"]}`}</code>
                . Use <code className="rounded bg-muted px-1">{`{}`}</code> to skip Entra role sync entirely
                (Team &amp; Access roles only). When a group matches, mapped roles are{" "}
                <span className="font-medium text-foreground">added</span> to existing roles on each Microsoft
                sign-in — they do not remove roles you assign in Team &amp; Access.
              </p>
            </div>

            <div className="flex flex-wrap gap-2 border-t border-border pt-4">
              <Button
                type="button"
                onClick={() => saveMutation.mutate()}
                disabled={saveMutation.isPending || !clientId.trim()}
              >
                {saveMutation.isPending ? "Saving…" : "Save sign-in settings"}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => testMutation.mutate()}
                disabled={testMutation.isPending || !clientId.trim() || !tenantIdentifier.trim()}
              >
                {testMutation.isPending ? "Checking…" : "Validate Microsoft app"}
              </Button>
            </div>
          </div>
        </EApprovalSectionCard>
      </div>
    </PermissionGate>
  );
}
