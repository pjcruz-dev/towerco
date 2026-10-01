"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  parseProductReleaseImport,
  PRODUCT_RELEASE_NOTES_KEY,
  PRODUCT_RELEASE_SEEN_KEY,
  type ProductRelease,
  type ProductReleaseSection,
} from "@/content/release-notes";
import { useProductRelease } from "@/hooks/use-product-release";
import { getErrorMessage } from "@/lib/api/error";
import {
  putSharedUserUiPreference,
  putUserUiPreference,
} from "@/lib/api/modules/user-ui-preferences-api";
import { hasAnyPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const EMPTY_SECTION: ProductReleaseSection = { heading: "", body: "" };

const IMPORT_PLACEHOLDER = `{
  "version": "v1.0.7",
  "title": "What changed",
  "summary": "One line for the dialog.",
  "sections": [
    { "heading": "My profile", "body": "Full details." }
  ]
}`;

function releaseJson(release: ProductRelease): string {
  return JSON.stringify(
    {
      version: release.version.trim(),
      title: release.title.trim(),
      summary: release.summary.trim(),
      sections: release.sections
        .map((section) => ({ heading: section.heading.trim(), body: section.body.trim() }))
        .filter((section) => section.heading || section.body),
    },
    null,
    2,
  );
}

function clampImportedRelease(release: ProductRelease): ProductRelease {
  return {
    version: release.version.slice(0, 40),
    title: release.title.slice(0, 160),
    summary: release.summary.slice(0, 500),
    sections:
      release.sections.length > 0
        ? release.sections.slice(0, 20).map((section) => ({
            heading: section.heading.slice(0, 160),
            body: section.body.slice(0, 4000),
          }))
        : [{ ...EMPTY_SECTION }],
  };
}

function cloneRelease(release: ProductRelease): ProductRelease {
  return {
    version: release.version,
    title: release.title,
    summary: release.summary,
    sections: release.sections.length > 0 ? release.sections.map((section) => ({ ...section })) : [{ ...EMPTY_SECTION }],
  };
}

export function ProductReleaseEditor() {
  const { release } = useProductRelease();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const notify = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<ProductRelease>(() => cloneRelease(release));
  const [importOpen, setImportOpen] = useState(false);
  const [importText, setImportText] = useState("");

  const canPublish = useMemo(() => {
    const scoped = user
      ? { ...user, permissions: activeTenantId ? effectivePermissions() : user.permissions }
      : null;
    return hasAnyPermission(scoped, [permissions.tenantManage, permissions.userManage]);
  }, [activeTenantId, effectivePermissions, user]);

  useEffect(() => {
    setDraft(cloneRelease(release));
  }, [release]);

  const publish = useMutation({
    mutationFn: async (next: ProductRelease) => {
      await putSharedUserUiPreference(PRODUCT_RELEASE_NOTES_KEY, { release: next });
      await putUserUiPreference(PRODUCT_RELEASE_SEEN_KEY, { version: next.version });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["me", "ui-preference", PRODUCT_RELEASE_NOTES_KEY] });
      void queryClient.invalidateQueries({ queryKey: ["me", "ui-preference", PRODUCT_RELEASE_SEEN_KEY] });
      notify({
        level: "success",
        title: "Update published",
        message: "People who have not seen this version will get it once, the next time they open the app.",
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not publish", message: getErrorMessage(error) });
    },
  });

  if (!canPublish) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          Current update {release.version}. You will see a new one once, the first time you open the app after it is published.
        </p>
        <ReleasePreview release={release} />
      </div>
    );
  }

  const copyJson = async () => {
    try {
      await navigator.clipboard.writeText(releaseJson(draft));
      notify({ level: "success", title: "JSON copied", message: "Paste it into a chat, revise it, then import the result." });
    } catch {
      notify({ level: "error", title: "Could not copy", message: "Select the import box and copy it manually." });
    }
  };

  const applyImport = () => {
    const parsed = parseProductReleaseImport(importText);
    if (!parsed) {
      notify({
        level: "error",
        title: "JSON not applied",
        message: "Need version, title, and a sections array. A { \"release\": { ... } } wrapper is fine.",
      });
      return;
    }
    setDraft(clampImportedRelease(parsed));
    notify({
      level: "success",
      title: "Note loaded",
      message: "The fields below match the JSON. Publish when it looks right.",
    });
  };

  const updateSection = (index: number, patch: Partial<ProductReleaseSection>) => {
    setDraft((current) => ({
      ...current,
      sections: current.sections.map((section, sectionIndex) =>
        sectionIndex === index ? { ...section, ...patch } : section,
      ),
    }));
  };

  return (
    <form
      className="space-y-4"
      onSubmit={(event) => {
        event.preventDefault();
        const next: ProductRelease = {
          version: draft.version.trim(),
          title: draft.title.trim(),
          summary: draft.summary.trim(),
          sections: draft.sections
            .map((section) => ({ heading: section.heading.trim(), body: section.body.trim() }))
            .filter((section) => section.heading || section.body),
        };
        if (!next.version || !next.title) {
          notify({ level: "error", title: "Version and title are required", message: "Add both before publishing." });
          return;
        }
        publish.mutate(next);
      }}
    >
      <p className="text-sm text-muted-foreground">
        Write the full note here. It shows once to every person in this organization, the first time they open the app
        after you publish a new version. Publish is what changes the version in the footer. Typing in this field does
        not. Editing text on a version people already dismissed will not show again — change the version to announce it.
      </p>
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={() => void copyJson()}>
          Copy JSON
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={() => setImportOpen((open) => !open)}>
          {importOpen ? "Hide import" : "Import JSON"}
        </Button>
      </div>
      {importOpen ? (
        <div className="space-y-2 rounded-lg border border-border p-3">
          <p className="text-xs text-muted-foreground">
            Paste a revised note. Apply fills the fields. It does not publish.
          </p>
          <Textarea
            value={importText}
            onChange={(event) => setImportText(event.target.value)}
            rows={8}
            placeholder={IMPORT_PLACEHOLDER}
            spellCheck={false}
            className="font-mono text-xs"
          />
          <Button type="button" size="sm" variant="secondary" onClick={applyImport}>
            Apply JSON
          </Button>
        </div>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="space-y-1.5 text-sm">
          <span className="font-medium text-foreground">Version</span>
          <Input
            value={draft.version}
            onChange={(event) => setDraft((current) => ({ ...current, version: event.target.value }))}
            placeholder="v1.0.5"
            maxLength={40}
          />
        </label>
        <label className="space-y-1.5 text-sm">
          <span className="font-medium text-foreground">Title</span>
          <Input
            value={draft.title}
            onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))}
            placeholder="What changed"
            maxLength={160}
          />
        </label>
      </div>
      <label className="block space-y-1.5 text-sm">
        <span className="font-medium text-foreground">Summary</span>
        <Textarea
          value={draft.summary}
          onChange={(event) => setDraft((current) => ({ ...current, summary: event.target.value }))}
          rows={2}
          maxLength={500}
        />
      </label>
      <div className="space-y-3">
        {draft.sections.map((section, index) => (
          <div key={index} className="space-y-2 rounded-lg border border-border p-3">
            <div className="flex items-center justify-between gap-2">
              <p className="text-xs font-medium text-muted-foreground">Section {index + 1}</p>
              {draft.sections.length > 1 ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() =>
                    setDraft((current) => ({
                      ...current,
                      sections: current.sections.filter((_, sectionIndex) => sectionIndex !== index),
                    }))
                  }
                >
                  Remove
                </Button>
              ) : null}
            </div>
            <Input
              value={section.heading}
              onChange={(event) => updateSection(index, { heading: event.target.value })}
              placeholder="Heading"
              maxLength={160}
            />
            <Textarea
              value={section.body}
              onChange={(event) => updateSection(index, { body: event.target.value })}
              placeholder="Full details"
              rows={4}
              maxLength={4000}
            />
          </div>
        ))}
        {draft.sections.length < 20 ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() =>
              setDraft((current) => ({ ...current, sections: [...current.sections, { ...EMPTY_SECTION }] }))
            }
          >
            Add section
          </Button>
        ) : null}
      </div>
      <Button type="submit" disabled={publish.isPending}>
        {publish.isPending ? "Publishing…" : "Publish update"}
      </Button>
    </form>
  );
}

function ReleasePreview({ release }: { release: ProductRelease }) {
  return (
    <div className="space-y-3 rounded-lg border border-border p-4">
      <p className="text-sm font-medium text-foreground">{release.title}</p>
      {release.summary ? <p className="text-sm text-muted-foreground">{release.summary}</p> : null}
      {release.sections.map((section) => (
        <div key={`${section.heading}-${section.body.slice(0, 24)}`}>
          {section.heading ? <p className="text-sm font-medium text-foreground">{section.heading}</p> : null}
          {section.body ? <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{section.body}</p> : null}
        </div>
      ))}
    </div>
  );
}
