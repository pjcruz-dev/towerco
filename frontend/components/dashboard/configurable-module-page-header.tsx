"use client";

import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  isActionVisible,
  resolvePageChrome,
  type PageChromeDefaults,
  type PageChromePrefs,
} from "@/lib/ui/page-chrome-config";
import { cn } from "@/lib/utils";

type Props = {
  defaults: PageChromeDefaults;
  prefs?: PageChromePrefs | null;
  editing?: boolean;
  onChromeChange?: (next: PageChromePrefs) => void;
  /** Render map for action ids that are visible. */
  renderActions: (ctx: {
    isVisible: (id: string) => boolean;
  }) => ReactNode;
  className?: string;
  /** Optional data-help on the root header. */
  dataHelp?: string;
};

/**
 * Module page header with optional Customize editing for title, description,
 * and which actions appear.
 */
export function ConfigurableModulePageHeader({
  defaults,
  prefs,
  editing = false,
  onChromeChange,
  renderActions,
  className,
  dataHelp,
}: Props) {
  const chrome = resolvePageChrome(defaults, prefs);

  const patch = (partial: PageChromePrefs) => {
    onChromeChange?.({
      title: prefs?.title,
      description: prefs?.description,
      hiddenActionIds: prefs?.hiddenActionIds,
      ...partial,
    });
  };

  const toggleAction = (id: string, show: boolean) => {
    const action = defaults.actions.find((item) => item.id === id);
    if (!action || action.hideable === false) return;
    const hidden = new Set(prefs?.hiddenActionIds ?? []);
    if (show) hidden.delete(id);
    else hidden.add(id);
    patch({ hiddenActionIds: [...hidden] });
  };

  return (
    <div className={cn("space-y-3", className)}>
      <header
        data-help={dataHelp}
        className="flex flex-wrap items-start justify-between gap-3"
      >
        <div className="min-w-0 max-w-2xl flex-1">
          {editing && onChromeChange ? (
            <div className="space-y-2">
              <div className="space-y-1">
                <Label htmlFor="page-chrome-title" className="text-xs">
                  Page title
                </Label>
                <Input
                  id="page-chrome-title"
                  value={prefs?.title ?? defaults.title}
                  onChange={(event) => patch({ title: event.target.value })}
                  className="h-9 text-base font-semibold"
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="page-chrome-description" className="text-xs">
                  Page description
                </Label>
                <textarea
                  id="page-chrome-description"
                  value={prefs?.description ?? defaults.description}
                  onChange={(event) => patch({ description: event.target.value })}
                  rows={2}
                  className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-muted-foreground"
                />
              </div>
            </div>
          ) : (
            <>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{chrome.title}</h1>
              {chrome.description ? (
                <p className="mt-1 text-sm text-muted-foreground">{chrome.description}</p>
              ) : null}
            </>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          {renderActions({
            isVisible: (id) => isActionVisible(chrome, id),
          })}
        </div>
      </header>

      {editing && onChromeChange ? (
        <div className="rounded-xl border border-dashed border-border bg-muted/20 px-3 py-2">
          <p className="text-xs font-medium text-muted-foreground">Header actions</p>
          <div className="mt-2 flex flex-wrap gap-3">
            {defaults.actions.map((action) => {
              const locked = action.hideable === false;
              const shown = isActionVisible(chrome, action.id);
              return (
                <label
                  key={action.id}
                  className={cn(
                    "flex items-center gap-2 text-xs text-foreground",
                    locked && "opacity-70",
                  )}
                >
                  <Checkbox
                    className="size-4"
                    checked={shown}
                    disabled={locked}
                    onCheckedChange={(value) => toggleAction(action.id, value === true)}
                  />
                  {action.label}
                  {locked ? <span className="text-muted-foreground">(required)</span> : null}
                </label>
              );
            })}
            {(prefs?.title || prefs?.description || (prefs?.hiddenActionIds?.length ?? 0) > 0) ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="h-7 text-xs"
                onClick={() => onChromeChange({})}
              >
                Reset header defaults
              </Button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
