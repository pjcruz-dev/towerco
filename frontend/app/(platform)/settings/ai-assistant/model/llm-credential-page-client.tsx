"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy } from "lucide-react";
import { useEffect, useState } from "react";

import { FilterSelect } from "@/components/forms/filter-select";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchLlmCredentials,
  saveLlmCredential,
  type LlmProviderCredential,
} from "@/lib/api/modules/assistant-api";
import { useNotificationStore } from "@/stores/notification-store";

const QUERY_KEY = ["assistant", "llm-credentials"] as const;
const TOKEN_PREFIX_LENGTH = 8;

function maskToken(token: string): string {
  if (token.length <= TOKEN_PREFIX_LENGTH) {
    return "*".repeat(Math.max(token.length, 8));
  }
  return token.slice(0, TOKEN_PREFIX_LENGTH) + "*".repeat(token.length - TOKEN_PREFIX_LENGTH);
}

function sourceLabel(provider: LlmProviderCredential | undefined): string {
  if (!provider) {
    return "Choose a model for this workspace.";
  }
  if (!provider.uses_token) {
    return `${provider.label} uses the server cloud credentials. No workspace token is required.`;
  }
  if (provider.source === "tenant") {
    return `${provider.label} is using a token saved for this workspace.`;
  }
  if (provider.source === "environment") {
    return `${provider.label} has no workspace token. The token below is the server environment key.`;
  }
  return `${provider.label} has no token yet. Paste one to use it for this workspace.`;
}

export function LlmCredentialPageClient() {
  const notify = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();
  const [provider, setProvider] = useState("cursor");
  const [token, setToken] = useState("");

  const credentialQuery = useQuery({
    queryKey: QUERY_KEY,
    queryFn: fetchLlmCredentials,
  });

  useEffect(() => {
    if (credentialQuery.data?.provider) {
      setProvider(credentialQuery.data.provider);
    }
  }, [credentialQuery.data?.provider]);

  const saveMutation = useMutation({
    mutationFn: saveLlmCredential,
    onSuccess: async (snapshot) => {
      setToken("");
      setProvider(snapshot.provider);
      await queryClient.setQueryData(QUERY_KEY, snapshot);
      notify({
        level: "success",
        title: "AI model saved",
        message: "This workspace will use the selected model.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save the AI model", message: getErrorMessage(error) }),
  });

  const snapshot = credentialQuery.data;
  const providers = snapshot?.providers ?? [];
  const selected = providers.find((item) => item.id === provider);
  const visibleToken = selected?.uses_token && selected.api_key ? selected.api_key : "";

  async function copyToken() {
    if (visibleToken === "") {
      return;
    }
    try {
      await navigator.clipboard.writeText(visibleToken);
      notify({ level: "success", title: "Token copied", message: "The full token is on the clipboard." });
    } catch {
      notify({
        level: "error",
        title: "Could not copy the token",
        message: "Allow clipboard access, then press Copy again.",
      });
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">AI model</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Pick the model for this workspace and save its token here. Cursor is ready to use now. Gemini and OpenAI
          accept their own tokens when you have them. A blank token field keeps the saved token.
        </p>
      </header>

      {credentialQuery.isLoading ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner className="size-4" />
          Loading model settings…
        </div>
      ) : null}

      {credentialQuery.isError ? (
        <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {getErrorMessage(credentialQuery.error)}
        </div>
      ) : null}

      {snapshot ? (
        <Card className="rounded-xl shadow-sm">
          <CardHeader>
            <CardTitle className="text-base font-medium">Models</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            {snapshot.providers.map((item) => (
              <p key={item.id}>
                <span className="font-medium text-foreground">{item.label}</span>
                {item.id === snapshot.provider ? " · active" : ""}
                {item.model ? ` · ${item.model}` : ""}
                {" · "}
                {item.uses_token ? (item.configured ? "token available" : "no token") : "server credentials"}
              </p>
            ))}
          </CardContent>
        </Card>
      ) : null}

      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Workspace model</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <FilterSelect id="assistant-llm-provider" label="Model" value={provider} onChange={setProvider}>
            {(providers.length > 0 ? providers : [{ id: "cursor", label: "Cursor", uses_token: true, configured: false, source: "missing" as const }]).map((item) => (
              <option key={item.id} value={item.id}>
                {item.label}
              </option>
            ))}
          </FilterSelect>
          <p className="text-sm text-muted-foreground">{sourceLabel(selected)}</p>
          {visibleToken !== "" ? (
            <div className="flex items-end gap-2">
              <div className="min-w-0 flex-1">
                <FormInput
                  id="assistant-current-token"
                  label="Current token"
                  name="current_api_key"
                  readOnly
                  value={maskToken(visibleToken)}
                  spellCheck={false}
                />
              </div>
              <Button type="button" variant="outline" onClick={() => void copyToken()}>
                <Copy className="size-4" />
                Copy
              </Button>
            </div>
          ) : null}
          {selected?.uses_token !== false ? (
            <FormInput
              label="API token"
              type="password"
              name="api_key"
              autoComplete="new-password"
              value={token}
              onChange={(event) => setToken(event.target.value)}
              placeholder={selected?.source === "tenant" ? "Token saved. Enter a new one to replace it." : "Paste the provider token"}
              disabled={saveMutation.isPending || credentialQuery.isLoading}
            />
          ) : null}
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={saveMutation.isPending || credentialQuery.isLoading}
              onClick={() =>
                saveMutation.mutate({
                  provider,
                  ...(token.trim() !== "" ? { api_key: token.trim() } : {}),
                })
              }
            >
              {saveMutation.isPending ? <Spinner className="size-4" /> : "Save"}
            </Button>
            {selected?.uses_token && selected.source === "tenant" ? (
              <Button
                type="button"
                variant="outline"
                disabled={saveMutation.isPending}
                onClick={() => saveMutation.mutate({ provider, clear: true })}
              >
                Clear workspace token
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
