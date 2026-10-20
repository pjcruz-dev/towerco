"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Monitor, Moon, Palette, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import { useEffect, useRef, useState, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { workspaceAppearanceQueryKey } from "@/components/layout/workspace-appearance-hydrator";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteUserUiPreference,
  putUserUiPreference,
} from "@/lib/api/modules/user-ui-preferences-api";
import {
  DEFAULT_TENANT_CHROME,
  isHex6,
  productPrimaryHex,
  readSwatchHex,
  TENANT_CHROME_PRESETS,
  type TenantChrome,
  type TenantChromePresetId,
} from "@/lib/theme/tenant-chrome";
import {
  serializeWorkspaceAppearance,
  WORKSPACE_APPEARANCE_KEY,
  type WorkspaceAppearance,
  type WorkspaceColorMode,
} from "@/lib/theme/workspace-appearance";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantAppearanceStore } from "@/stores/tenant-appearance-store";
import { useNotificationStore } from "@/stores/notification-store";

const COLOR_MODES: { value: WorkspaceColorMode; label: string; icon: typeof Sun }[] = [
  { value: "light", label: "Light", icon: Sun },
  { value: "dark", label: "Dark", icon: Moon },
  { value: "system", label: "System", icon: Monitor },
];

function chromeFrom(source: WorkspaceAppearance | null): TenantChrome {
  if (source?.saved) {
    return source;
  }
  return { ...DEFAULT_TENANT_CHROME };
}

export function TenantAppearanceCustomizer() {
  const userId = useAuthStore((state) => state.user?.id);
  const open = useTenantAppearanceStore((state) => state.open);
  const setOpen = useTenantAppearanceStore((state) => state.setOpen);
  const setPreview = useTenantAppearanceStore((state) => state.setPreview);
  const saved = useTenantAppearanceStore((state) => state.saved);
  const setSaved = useTenantAppearanceStore((state) => state.setSaved);
  const notify = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();
  const { theme, setTheme } = useTheme();

  const [draft, setDraft] = useState<TenantChrome>(DEFAULT_TENANT_CHROME);
  const [colorMode, setColorMode] = useState<WorkspaceColorMode>("system");
  const [dirty, setDirty] = useState(false);
  const [themeReady, setThemeReady] = useState(false);
  const colorRef = useRef<HTMLInputElement>(null);
  const swatchRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const themeOnOpen = useRef<WorkspaceColorMode>("system");
  const skipThemeRestore = useRef(false);
  const openedOnce = useRef(false);
  const themeRef = useRef(theme);
  themeRef.current = theme;

  useEffect(() => {
    setThemeReady(true);
  }, []);

  useEffect(() => {
    if (!open) {
      openedOnce.current = false;
      setDirty(false);
      return;
    }
    if (!openedOnce.current) {
      openedOnce.current = true;
      themeOnOpen.current = isColorMode(themeRef.current) ? themeRef.current : "system";
    }
    if (dirty) {
      return;
    }
    setDraft(chromeFrom(saved));
    setColorMode(saved?.colorMode ?? themeOnOpen.current);
  }, [dirty, open, saved]);

  useEffect(() => {
    if (!open) {
      return;
    }
    setPreview({ ...draft, saved: true });
  }, [draft, open, setPreview]);

  useEffect(() => {
    if (!open || !themeReady) {
      return;
    }
    setTheme(colorMode);
  }, [colorMode, open, setTheme, themeReady]);

  const saveMutation = useMutation({
    mutationFn: () => {
      const appearance: WorkspaceAppearance = {
        ...draft,
        saved: true,
        colorMode,
        primary: draft.primary_preset === "custom" ? draft.primary : null,
      };
      return putUserUiPreference(WORKSPACE_APPEARANCE_KEY, serializeWorkspaceAppearance(appearance)).then(
        () => appearance,
      );
    },
    onSuccess: (appearance) => {
      setSaved(appearance);
      setPreview(null);
      setDirty(false);
      skipThemeRestore.current = true;
      void queryClient.invalidateQueries({ queryKey: workspaceAppearanceQueryKey(userId) });
      notify({
        level: "success",
        title: "Your workspace design was saved",
        message: "Only your account uses this color, skin, and layout.",
      });
      setOpen(false);
    },
    onError: (error) => {
      notify({
        level: "error",
        title: "Could not save appearance",
        message: getErrorMessage(error),
      });
    },
  });

  const resetMutation = useMutation({
    mutationFn: () => deleteUserUiPreference(WORKSPACE_APPEARANCE_KEY),
    onSuccess: () => {
      setSaved(null);
      setDraft({ ...DEFAULT_TENANT_CHROME });
      setColorMode("system");
      setPreview(null);
      setDirty(false);
      skipThemeRestore.current = true;
      setTheme("system");
      void queryClient.invalidateQueries({ queryKey: workspaceAppearanceQueryKey(userId) });
      notify({
        level: "success",
        title: "Your workspace design was reset",
        message: "This account is back to the standard layout.",
      });
      setOpen(false);
    },
    onError: (error) => {
      notify({
        level: "error",
        title: "Could not reset appearance",
        message: getErrorMessage(error),
      });
    },
  });

  function update(next: Partial<TenantChrome>) {
    setDirty(true);
    setDraft((current) => ({ ...current, ...next }));
  }

  function selectPreset(preset: TenantChromePresetId) {
    update({ primary_preset: preset, primary: null });
  }

  function selectCustom() {
    const seed = isHex6(draft.primary)
      ? draft.primary
      : readSwatchHex(
          draft.primary_preset && draft.primary_preset !== "custom" ? draft.primary_preset : "teal",
        ) ?? productPrimaryHex();
    update({ primary_preset: "custom", primary: seed });
    window.setTimeout(() => colorRef.current?.click(), 0);
  }

  function reset() {
    resetMutation.mutate();
  }

  const busy = saveMutation.isPending || resetMutation.isPending;

  return (
    <Sheet
      open={open}
      onOpenChange={(next) => {
        if (!next && !skipThemeRestore.current) {
          setTheme(themeOnOpen.current);
        }
        skipThemeRestore.current = false;
        setOpen(next);
      }}
    >
      <SheetContent className="w-full gap-0 overflow-hidden sm:max-w-sm">
        <SheetHeader>
          <SheetTitle>Customize workspace</SheetTitle>
          <SheetDescription>
            Color, skin, layout, and content width for your account. Other people keep their own design.
          </SheetDescription>
        </SheetHeader>

        <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-4">
          <section className="space-y-3">
            <h2 className="text-sm font-medium text-foreground">Theming</h2>
            <p className="text-xs font-medium text-muted-foreground">Appearance</p>
            <div className="grid grid-cols-3 gap-1 rounded-lg border border-border bg-muted/30 p-1">
              {COLOR_MODES.map((option) => {
                const Icon = option.icon;
                const selected = colorMode === option.value;
                return (
                  <button
                    key={option.value}
                    type="button"
                    disabled={!themeReady}
                    onClick={() => {
                      setDirty(true);
                      setColorMode(option.value);
                    }}
                    className={cn(
                      "inline-flex flex-col items-center gap-1 rounded-md px-2 py-2 text-[11px] font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
                      selected
                        ? "bg-background text-foreground shadow-sm"
                        : "text-muted-foreground hover:bg-background/70 hover:text-foreground",
                    )}
                  >
                    <Icon className="size-3.5" aria-hidden />
                    {option.label}
                  </button>
                );
              })}
            </div>
            <p className="text-xs font-medium text-muted-foreground">Primary color</p>
            <div role="radiogroup" aria-label="Primary color" className="flex flex-wrap items-center gap-2">
              {TENANT_CHROME_PRESETS.map((preset, index) => {
                const selected = draft.primary_preset === preset.id;
                return (
                  <button
                    key={preset.id}
                    ref={(node) => {
                      swatchRefs.current[index] = node;
                    }}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    aria-label={preset.label}
                    onClick={() => selectPreset(preset.id)}
                    onKeyDown={(event) => {
                      const delta =
                        event.key === "ArrowRight" || event.key === "ArrowDown"
                          ? 1
                          : event.key === "ArrowLeft" || event.key === "ArrowUp"
                            ? -1
                            : 0;
                      if (delta === 0) {
                        return;
                      }
                      event.preventDefault();
                      const next =
                        (index + delta + TENANT_CHROME_PRESETS.length) % TENANT_CHROME_PRESETS.length;
                      const item = TENANT_CHROME_PRESETS[next];
                      selectPreset(item.id);
                      swatchRefs.current[next]?.focus();
                    }}
                    className={cn(
                      "size-9 rounded-md border border-transparent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
                      selected && "ring-2 ring-foreground ring-offset-2 ring-offset-background",
                    )}
                    style={{ backgroundColor: `var(--chrome-swatch-${preset.id})` }}
                  />
                );
              })}
              <button
                type="button"
                aria-label="Custom primary color"
                aria-pressed={draft.primary_preset === "custom"}
                onClick={selectCustom}
                className={cn(
                  "inline-flex size-9 items-center justify-center rounded-full border border-border bg-card text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
                  draft.primary_preset === "custom" &&
                    "ring-2 ring-foreground ring-offset-2 ring-offset-background",
                )}
                style={
                  draft.primary_preset === "custom" && isHex6(draft.primary)
                    ? { backgroundColor: draft.primary, color: "var(--primary-foreground)" }
                    : undefined
                }
              >
                <Palette className="size-4" aria-hidden />
              </button>
              <input
                ref={colorRef}
                type="color"
                className="sr-only"
                tabIndex={-1}
                aria-label="Custom primary color value"
                value={isHex6(draft.primary) ? draft.primary : productPrimaryHex()}
                onChange={(event) => {
                  update({ primary_preset: "custom", primary: event.target.value });
                }}
              />
            </div>
          </section>

          <OptionSection
            title="Skin"
            value={draft.skin}
            options={[
              { value: "default", label: "Default", thumb: <SkinThumb bordered={false} /> },
              { value: "bordered", label: "Bordered", thumb: <SkinThumb bordered /> },
            ]}
            onChange={(skin) => update({ skin })}
          />

          <OptionSection
            title="Layout"
            value={draft.layout}
            columns={3}
            options={[
              { value: "vertical", label: "Vertical", thumb: <LayoutThumb kind="vertical" /> },
              { value: "collapsed", label: "Collapsed", thumb: <LayoutThumb kind="collapsed" /> },
              { value: "horizontal", label: "Horizontal", thumb: <LayoutThumb kind="horizontal" /> },
            ]}
            onChange={(layout) => update({ layout })}
          />

          <OptionSection
            title="Content"
            value={draft.content}
            options={[
              { value: "compact", label: "Compact", thumb: <ContentThumb wide={false} /> },
              { value: "wide", label: "Wide", thumb: <ContentThumb wide /> },
            ]}
            onChange={(content) => update({ content })}
          />
        </div>

        <SheetFooter className="border-t border-border sm:flex-row sm:justify-between">
          <Button type="button" variant="outline" disabled={busy} onClick={reset}>
            {resetMutation.isPending ? "Resetting…" : "Reset"}
          </Button>
          <Button type="button" disabled={busy} onClick={() => saveMutation.mutate()}>
            {saveMutation.isPending ? "Saving…" : "Save"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

function isColorMode(value: string | undefined): value is WorkspaceColorMode {
  return value === "light" || value === "dark" || value === "system";
}

function OptionSection<T extends string>({
  title,
  value,
  options,
  columns = 2,
  onChange,
}: {
  title: string;
  value: T;
  columns?: 2 | 3;
  options: { value: T; label: string; thumb: ReactNode }[];
  onChange: (value: T) => void;
}) {
  return (
    <section className="space-y-2">
      <h2 className="text-sm font-medium text-foreground">{title}</h2>
      <div
        role="radiogroup"
        aria-label={title}
        className={cn("grid gap-2", columns === 3 ? "grid-cols-3" : "grid-cols-2")}
      >
        {options.map((option) => {
          const selected = value === option.value;
          return (
            <button
              key={option.value}
              type="button"
              role="radio"
              aria-checked={selected}
              onClick={() => onChange(option.value)}
              className={cn(
                "flex flex-col gap-1.5 rounded-lg border bg-card p-1.5 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
                selected ? "border-primary ring-1 ring-primary" : "border-border hover:bg-muted/40",
              )}
            >
              {option.thumb}
              <span className="text-center text-xs font-medium text-foreground">{option.label}</span>
            </button>
          );
        })}
      </div>
    </section>
  );
}

function SkinThumb({ bordered }: { bordered: boolean }) {
  return (
    <div
      className={cn(
        "flex h-14 w-full flex-col gap-1 rounded-md bg-muted/50 p-1.5",
        bordered && "border border-border",
      )}
      aria-hidden
    >
      <div className={cn("h-2 w-8 rounded-sm bg-muted-foreground/30", bordered && "border border-border")} />
      <div className="flex flex-1 gap-1">
        <div className={cn("flex-1 rounded-sm bg-background", bordered && "border border-border")} />
        <div className={cn("w-5 rounded-sm bg-background", bordered && "border border-border")} />
      </div>
    </div>
  );
}

function LayoutThumb({ kind }: { kind: "vertical" | "collapsed" | "horizontal" }) {
  if (kind === "horizontal") {
    return (
      <div className="flex h-14 w-full flex-col overflow-hidden rounded-md border border-border bg-muted/40" aria-hidden>
        <div className="h-2.5 bg-muted-foreground/25" />
        <div className="m-1.5 flex-1 rounded-sm bg-background" />
      </div>
    );
  }

  return (
    <div className="flex h-14 w-full overflow-hidden rounded-md border border-border bg-muted/40" aria-hidden>
      <div className={cn("h-full bg-muted-foreground/25", kind === "collapsed" ? "w-2" : "w-5")} />
      <div className="m-1.5 flex-1 rounded-sm bg-background" />
    </div>
  );
}

function ContentThumb({ wide }: { wide: boolean }) {
  return (
    <div className="flex h-14 w-full items-stretch overflow-hidden rounded-md border border-border bg-muted/40 p-1.5" aria-hidden>
      <div className={cn("rounded-sm bg-background", wide ? "w-full" : "mx-auto w-2/3")} />
    </div>
  );
}
