"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Monitor, Moon, Palette, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import { useEffect, useRef, useState, type ReactNode, type RefObject } from "react";

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
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { updateTenantModuleAccents } from "@/lib/api/modules/admin-api";
import {
  deleteUserUiPreference,
  putUserUiPreference,
} from "@/lib/api/modules/user-ui-preferences-api";
import { MODULE_ACCENT_TARGETS, moduleStyleFor, type ModuleAccentMap } from "@/lib/theme/module-accents";
import {
  DEFAULT_TENANT_CHROME,
  isHex6,
  parseColorInput,
  productPrimaryHex,
  readSwatchHex,
  TENANT_CHROME_PRESET_HEX,
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
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";
import { useNotificationStore } from "@/stores/notification-store";

const COLOR_MODES: { value: WorkspaceColorMode; label: string; icon: typeof Sun }[] = [
  { value: "light", label: "Light", icon: Sun },
  { value: "dark", label: "Dark", icon: Moon },
  { value: "system", label: "System", icon: Monitor },
];

type ModuleColorDraft = {
  preset: TenantChromePresetId | "plain" | "custom";
  accent: string;
  sidebar: string;
  background: string;
  card: string;
};

function presetForHex(hex: string): TenantChromePresetId | null {
  const match = TENANT_CHROME_PRESETS.find((preset) => TENANT_CHROME_PRESET_HEX[preset.id] === hex);
  return match?.id ?? null;
}

function HexColorField({
  label,
  value,
  fallback,
  onCommit,
  allowClear,
  inputRef,
}: {
  label: string;
  value: string;
  fallback: string;
  onCommit: (hex: string) => void;
  allowClear?: boolean;
  inputRef?: RefObject<HTMLInputElement | null>;
}) {
  const shown = isHex6(value) ? value.toUpperCase() : "";
  const [text, setText] = useState(shown);
  const [focused, setFocused] = useState(false);
  const [synced, setSynced] = useState(shown);
  if (!focused && synced !== shown) {
    setSynced(shown);
    setText(shown);
  }

  const swatch = isHex6(value) ? value : fallback;

  return (
    <div className="flex items-center gap-1.5">
      <span className="w-14 shrink-0 text-[11px] text-muted-foreground">{label}</span>
      <input
        type="color"
        aria-label={`${label} color`}
        className="size-7 shrink-0 cursor-pointer rounded border border-border bg-white p-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        value={swatch}
        onChange={(event) => onCommit(event.target.value)}
      />
      <input
        ref={inputRef}
        type="text"
        spellCheck={false}
        aria-label={`${label} hex`}
        placeholder="#0D9488"
        maxLength={22}
        className="h-7 min-w-0 flex-1 rounded-md border border-border bg-background px-2 font-mono text-xs text-foreground uppercase placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        value={text}
        onFocus={() => setFocused(true)}
        onChange={(event) => {
          const next = event.target.value;
          setText(next);
          const parsed = parseColorInput(next);
          if (parsed) {
            onCommit(parsed);
          } else if (allowClear && next.trim() === "") {
            onCommit("");
          }
        }}
        onBlur={() => {
          setFocused(false);
          const parsed = parseColorInput(text);
          if (parsed) {
            onCommit(parsed);
            setText(parsed.toUpperCase());
            return;
          }
          if (allowClear && text.trim() === "") {
            onCommit("");
            setText("");
            return;
          }
          setText(shown);
        }}
      />
      {allowClear && value ? (
        <button
          type="button"
          className="shrink-0 text-[10px] text-foreground underline"
          onClick={() => onCommit("")}
        >
          Default
        </button>
      ) : null}
    </div>
  );
}

function chromeFrom(source: WorkspaceAppearance | null): TenantChrome {
  if (source?.saved) {
    return source;
  }
  return { ...DEFAULT_TENANT_CHROME };
}

export function TenantAppearanceCustomizer() {
  const user = useAuthStore((state) => state.user);
  const userId = user?.id;
  const canSetModuleColors = hasPermission(user, [permissions.tenantManage]);
  const branding = useTenantBrandingStore((state) => state.branding);
  const setBranding = useTenantBrandingStore((state) => state.setBranding);
  const open = useTenantAppearanceStore((state) => state.open);
  const setOpen = useTenantAppearanceStore((state) => state.setOpen);
  const setPreview = useTenantAppearanceStore((state) => state.setPreview);
  const setModulePreview = useTenantAppearanceStore((state) => state.setModulePreview);
  const saved = useTenantAppearanceStore((state) => state.saved);
  const setSaved = useTenantAppearanceStore((state) => state.setSaved);
  const notify = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();
  const { theme, setTheme } = useTheme();

  const [draft, setDraft] = useState<TenantChrome>(DEFAULT_TENANT_CHROME);
  const [moduleAccents, setModuleAccents] = useState<Record<string, ModuleColorDraft>>({});
  const [colorMode, setColorMode] = useState<WorkspaceColorMode>("system");
  const [dirty, setDirty] = useState(false);
  const [themeReady, setThemeReady] = useState(false);
  const primaryHexRef = useRef<HTMLInputElement>(null);
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
    const accents: Record<string, ModuleColorDraft> = {};
    for (const shell of MODULE_ACCENT_TARGETS) {
      const style = moduleStyleFor(branding?.module_accents, shell.id);
      const preset = style?.primary_preset;
      accents[shell.id] = {
        preset: preset && preset !== "plain" ? preset : "plain",
        accent: style?.primary ?? "",
        sidebar: style?.sidebar ?? "",
        background: style?.background ?? "",
        card: style?.card ?? "",
      };
    }
    setModuleAccents(accents);
  }, [branding?.module_accents, dirty, open, saved]);

  useEffect(() => {
    if (!open) {
      return;
    }
    setPreview({ ...draft, saved: true });
  }, [draft, open, setPreview]);

  useEffect(() => {
    if (!open || !canSetModuleColors || Object.keys(moduleAccents).length === 0) {
      return;
    }
    const previewAccents: ModuleAccentMap = {};
    for (const [moduleId, style] of Object.entries(moduleAccents)) {
      previewAccents[moduleId] = {
        primary_preset: style.preset === "plain" ? null : style.preset,
        primary: style.preset === "custom" && isHex6(style.accent) ? style.accent : null,
        sidebar: style.sidebar || null,
        background: style.background || null,
        card: style.card || null,
      };
    }
    setModulePreview(previewAccents);
  }, [canSetModuleColors, moduleAccents, open, setModulePreview]);

  useEffect(() => {
    if (!open || !themeReady) {
      return;
    }
    setTheme(colorMode);
  }, [colorMode, open, setTheme, themeReady]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const appearance: WorkspaceAppearance = {
        ...draft,
        saved: true,
        colorMode,
        primary: draft.primary_preset === "custom" ? draft.primary : null,
      };
      await putUserUiPreference(WORKSPACE_APPEARANCE_KEY, serializeWorkspaceAppearance(appearance));
      const moduleBranding = canSetModuleColors
        ? await updateTenantModuleAccents(
            Object.fromEntries(
              Object.entries(moduleAccents).map(([moduleId, style]) => [
                moduleId,
                {
                  primary_preset: style.preset,
                  primary: style.preset === "custom" && isHex6(style.accent) ? style.accent : null,
                  sidebar: style.sidebar || null,
                  background: style.background || null,
                  card: style.card || null,
                },
              ]),
            ),
          )
        : null;
      return { appearance, moduleBranding };
    },
    onSuccess: ({ appearance, moduleBranding }) => {
      setSaved(appearance);
      setPreview(null);
      setDirty(false);
      skipThemeRestore.current = true;
      if (moduleBranding) {
        setBranding({ ...(branding ?? moduleBranding), ...moduleBranding });
      }
      void queryClient.invalidateQueries({ queryKey: workspaceAppearanceQueryKey(userId) });
      notify({
        level: "success",
        title: "Your workspace design was saved",
        message: canSetModuleColors
          ? "Module accents apply to everyone. Skin, layout, and light or dark stay on your account."
          : "Only your account uses this skin and layout.",
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

  function applyPrimaryHex(hex: string) {
    if (!isHex6(hex)) {
      update({ primary_preset: null, primary: null });
      return;
    }
    const preset = presetForHex(hex);
    update(preset ? { primary_preset: preset, primary: null } : { primary_preset: "custom", primary: hex });
  }

  function selectCustom() {
    const seed = isHex6(draft.primary)
      ? draft.primary
      : readSwatchHex(
          draft.primary_preset && draft.primary_preset !== "custom" ? draft.primary_preset : "teal",
        ) ?? productPrimaryHex();
    update({ primary_preset: "custom", primary: seed });
    window.setTimeout(() => primaryHexRef.current?.focus(), 0);
  }

  function applyModuleHex(moduleId: string, style: ModuleColorDraft, key: "accent" | "sidebar" | "background" | "card", hex: string) {
    setDirty(true);
    setModuleAccents((current) => {
      const base = current[moduleId] ?? style;
      if (key !== "accent") {
        return { ...current, [moduleId]: { ...base, [key]: hex } };
      }
      const preset = isHex6(hex) ? presetForHex(hex) : null;
      return {
        ...current,
        [moduleId]: {
          ...base,
          preset: hex === "" ? "plain" : (preset ?? "custom"),
          accent: preset || hex === "" ? "" : hex,
        },
      };
    });
  }

  function reset() {
    resetMutation.mutate();
  }

  const busy = saveMutation.isPending || resetMutation.isPending;

  return (
    <Sheet
      modal={false}
      disablePointerDismissal
      open={open}
      onOpenChange={(next) => {
        if (!next && !skipThemeRestore.current) {
          setTheme(themeOnOpen.current);
        }
        skipThemeRestore.current = false;
        setOpen(next);
      }}
    >
      <SheetContent showOverlay={false} className="w-full gap-0 overflow-hidden shadow-xl sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Customize workspace</SheetTitle>
          <SheetDescription>
            Live preview. Light, dark, color, skin, layout, and width update this page as you change them.
            {canSetModuleColors
              ? " Open another module to preview that module. Save to keep module colors for everyone."
              : " Save to keep skin, layout, and light or dark on your account."}
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
            </div>
            <HexColorField
              label="Color"
              inputRef={primaryHexRef}
              allowClear
              fallback={productPrimaryHex()}
              value={
                draft.primary_preset === "custom" && isHex6(draft.primary)
                  ? draft.primary
                  : draft.primary_preset && draft.primary_preset !== "custom"
                    ? TENANT_CHROME_PRESET_HEX[draft.primary_preset]
                    : ""
              }
              onCommit={applyPrimaryHex}
            />
            <p className="text-[11px] text-muted-foreground">Type a hex color such as #0D9488, or RGB such as 13, 148, 136.</p>
            {canSetModuleColors ? (
              <div className="space-y-3">
                <p className="text-xs font-medium text-muted-foreground">Module accents</p>
                <p className="text-xs text-muted-foreground">
                  Type a hex color such as #0D9488, or RGB such as 13, 148, 136. Plain keeps the white page. Success, warning, and error stay the same.
                </p>
                {MODULE_ACCENT_TARGETS.map((shell) => {
                  const style = moduleAccents[shell.id] ?? {
                    preset: "plain" as const,
                    accent: "",
                    sidebar: "",
                    background: "",
                    card: "",
                  };
                  const accentHex =
                    style.preset === "custom" && isHex6(style.accent)
                      ? style.accent
                      : style.preset !== "plain" && style.preset !== "custom"
                        ? TENANT_CHROME_PRESET_HEX[style.preset]
                        : "";
                  return (
                    <div key={shell.id} className="space-y-2 rounded-lg border border-border p-2">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-sm text-foreground">{shell.title}</span>
                        <div className="flex items-center gap-1.5" role="radiogroup" aria-label={`${shell.title} accent`}>
                          <button
                            type="button"
                            role="radio"
                            aria-checked={style.preset === "plain"}
                            aria-label={`${shell.title} plain`}
                            onClick={() => {
                              setDirty(true);
                              setModuleAccents((current) => ({
                                ...current,
                                [shell.id]: { ...style, preset: "plain", accent: "" },
                              }));
                            }}
                            className={cn(
                              "size-6 rounded-md border border-border bg-white focus-visible:ring-2 focus-visible:ring-ring",
                              style.preset === "plain" && "ring-2 ring-foreground ring-offset-2 ring-offset-background",
                            )}
                          />
                          {TENANT_CHROME_PRESETS.map((preset) => {
                            const selected = style.preset === preset.id;
                            return (
                              <button
                                key={preset.id}
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                aria-label={`${shell.title} ${preset.label}`}
                                onClick={() => {
                                  setDirty(true);
                                  setModuleAccents((current) => ({
                                    ...current,
                                    [shell.id]: { ...style, preset: preset.id, accent: "" },
                                  }));
                                }}
                                className={cn(
                                  "size-6 rounded-md border border-transparent focus-visible:ring-2 focus-visible:ring-ring",
                                  selected && "ring-2 ring-foreground ring-offset-2 ring-offset-background",
                                )}
                                style={{ backgroundColor: `var(--chrome-swatch-${preset.id})` }}
                              />
                            );
                          })}
                        </div>
                      </div>
                      <div className="space-y-1.5">
                        <HexColorField
                          label="Accent"
                          allowClear
                          fallback="#ffffff"
                          value={accentHex}
                          onCommit={(hex) => applyModuleHex(shell.id, style, "accent", hex)}
                        />
                        {(
                          [
                            ["sidebar", "Sidebar"],
                            ["background", "Page"],
                            ["card", "Cards"],
                          ] as const
                        ).map(([key, label]) => (
                          <HexColorField
                            key={key}
                            label={label}
                            allowClear
                            fallback="#ffffff"
                            value={style[key]}
                            onCommit={(hex) => applyModuleHex(shell.id, style, key, hex)}
                          />
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : null}
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
