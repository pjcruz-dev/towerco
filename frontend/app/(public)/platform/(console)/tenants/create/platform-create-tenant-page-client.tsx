"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Info } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { FormInput } from "@/components/forms/form-input";
import { CreateTenantHostPreview } from "@/components/platform/create-tenant-host-preview";
import { TenantCredentialsPanel } from "@/components/platform/tenant-credentials-panel";
import { TenantEnvironmentPicker } from "@/components/platform/tenant-environment-picker";
import {
  TenantModulesPicker,
  type TenantModulesPickerValue,
} from "@/components/platform/tenant-modules-picker";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateTenant,
  platformListRolloutPlaybooks,
  type CreateTenantResponse,
} from "@/lib/api/modules/platform-api";
import {
  deriveTenantIdentityFromHost,
  getLocalhostDefaultBrandDomain,
  normalizeBrandDomain,
  normalizeTenantSlug,
} from "@/lib/tenant/derive-tenant-identity";
import { rememberDevTenantDomain } from "@/lib/tenant/resolve-tenant-domain";
import {
  isLocalDevPlatformHost,
  recommendedTenantDomain,
  type TenantEnvironment,
} from "@/lib/tenant/recommended-tenant-domain";
import { filterSelectClassName } from "@/lib/ui/field-control";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const schema = z
  .object({
    domain: z.string().min(1, "Hostname is required"),
    tenant_id: z
      .string()
      .optional()
      .refine((v) => !v?.trim() || z.string().uuid().safeParse(v.trim()).success, {
        message: "Must be a valid UUID or left blank",
      }),
    slug: z.string().min(1, "Slug is required"),
    brand_domain: z.string().optional(),
    environment: z.enum(["local", "test", "staging", "production"]),
    tco_sequence_prefix: z.string().max(8).optional(),
    playbook_version_id: z.string().optional(),
    seed: z.boolean(),
  })
  .superRefine((values, ctx) => {
    const needsBrand =
      !isLocalDevPlatformHost() &&
      ["test", "staging", "production"].includes(values.environment);
    if (needsBrand && !values.brand_domain?.trim()) {
      ctx.addIssue({
        code: "custom",
        path: ["brand_domain"],
        message: "Brand domain is required for test, staging, and production",
      });
    }
  });

type FormValues = z.infer<typeof schema>;

const localhostBrandDefault = getLocalhostDefaultBrandDomain();

export function PlatformCreateTenantPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);
  const [created, setCreated] = useState<CreateTenantResponse | null>(null);
  const [enabledModules, setEnabledModules] = useState<TenantModulesPickerValue>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  const playbooksQuery = useQuery({
    queryKey: ["platform", "rollout-playbooks"],
    queryFn: platformListRolloutPlaybooks,
    enabled: Boolean(isHydrated && accessToken),
  });

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      domain: "",
      tenant_id: "",
      slug: "",
      brand_domain: "",
      environment: "local",
      tco_sequence_prefix: "A",
      playbook_version_id: "",
      seed: false,
    },
  });

  const mutation = useMutation({
    mutationFn: platformCreateTenant,
    onSuccess: (data) => {
      setCreated(data);
      if (data.domain) {
        rememberDevTenantDomain(data.domain);
      }
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      notify({
        level: "success",
        title: "Tenant created",
        message: `Tenant ID ${data.tenant_id}`,
      });
    },
    onError: (error) => {
      notify({
        level: "error",
        title: "Provisioning failed",
        message: getErrorMessage(error),
      });
    },
  });

  const seed = form.watch("seed");
  const domain = form.watch("domain");
  const slug = form.watch("slug");
  const brandDomain = form.watch("brand_domain");
  const environment = form.watch("environment");
  const playbookVersions = (playbooksQuery.data?.versions ?? []).filter(
    (version) => version.published_at,
  );

  const [hostnameCustomized, setHostnameCustomized] = useState(false);
  const slugLocked = useRef(false);
  const brandLocked = useRef(false);
  const domainLocked = useRef(false);
  const { setValue, getValues, register } = form;

  const domainField = register("domain");
  const slugField = register("slug");
  const brandField = register("brand_domain");

  useEffect(() => {
    if (!domainLocked.current) {
      return;
    }
    const derived = deriveTenantIdentityFromHost(domain);
    if (!slugLocked.current && getValues("slug") !== derived.slug) {
      setValue("slug", derived.slug, { shouldDirty: false, shouldValidate: true });
    }
    if (!brandLocked.current && getValues("brand_domain") !== derived.brandDomain) {
      setValue("brand_domain", derived.brandDomain, {
        shouldDirty: false,
        shouldValidate: true,
      });
    }
  }, [domain, getValues, setValue]);

  useEffect(() => {
    const normalizedSlug = normalizeTenantSlug(slug ?? "");
    if (!normalizedSlug || domainLocked.current) {
      return;
    }
    const recommended = recommendedTenantDomain(
      environment,
      normalizedSlug,
      brandDomain?.trim() || null,
    );
    if (getValues("domain") === recommended) {
      return;
    }
    setValue("domain", recommended, { shouldDirty: false, shouldValidate: true });
  }, [environment, slug, brandDomain, getValues, setValue]);

  const applyRecommendedHostname = () => {
    const normalizedSlug = normalizeTenantSlug(getValues("slug") ?? "");
    if (!normalizedSlug) {
      return;
    }
    domainLocked.current = false;
    setHostnameCustomized(false);
    setValue(
      "domain",
      recommendedTenantDomain(
        getValues("environment"),
        normalizedSlug,
        getValues("brand_domain")?.trim() || null,
      ),
      { shouldDirty: true, shouldValidate: true },
    );
  };

  const handleEnvironmentChange = (next: TenantEnvironment) => {
    domainLocked.current = false;
    setHostnameCustomized(false);
    setValue("environment", next, { shouldDirty: true, shouldValidate: true });
  };

  const syncIdentityLocksFromValues = () => {
    const derived = deriveTenantIdentityFromHost(getValues("domain") ?? "");
    const slugValue = normalizeTenantSlug(getValues("slug") ?? "");
    const brand = normalizeBrandDomain(getValues("brand_domain") ?? "");
    slugLocked.current = slugValue !== "" && slugValue !== derived.slug;
    brandLocked.current = brand !== "" && brand !== derived.brandDomain;
  };

  const domainExamples = useMemo(() => {
    const brandHint = localhostBrandDefault || "example.com";
    return [
      { slug: "atc", env: "local" as const, label: `Local · atc.localhost` },
      { slug: "atc", env: "test" as const, label: `Test · test.${brandHint}` },
      { slug: "atc", env: "production" as const, label: `App · app.${brandHint}` },
    ];
  }, [localhostBrandDefault]);

  const applyQuickExample = (example: { slug: string; env: TenantEnvironment }) => {
    slugLocked.current = false;
    brandLocked.current = false;
    domainLocked.current = false;
    setHostnameCustomized(false);
    setValue("slug", example.slug, { shouldDirty: true, shouldValidate: true });
    setValue("environment", example.env, { shouldDirty: true, shouldValidate: true });
    if (localhostBrandDefault) {
      setValue("brand_domain", localhostBrandDefault, { shouldDirty: true, shouldValidate: true });
    }
  };

  const onSubmit = (values: FormValues) => {
    const tenantId = values.tenant_id?.trim() || undefined;
    const playbookVersionId = values.playbook_version_id?.trim() || undefined;
    mutation.mutate({
      domain: values.domain.trim(),
      tenant_id: tenantId,
      slug: values.slug.trim(),
      brand_domain: values.brand_domain?.trim() || undefined,
      environment: values.environment,
      tco_sequence_prefix: values.tco_sequence_prefix?.trim() || undefined,
      playbook_version_id: playbookVersionId,
      enabled_modules: enabledModules,
      migrate: true,
      seed: values.seed,
    });
  };

  if (!isHydrated || !accessToken) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Loading…
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Create tenant</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Provision a new organization tenant for any environment — local, test, staging, or production.
          Rollout policy and PH holidays apply automatically. Add linked environments later from the
          tenant directory without changing slug or brand.
        </p>
      </div>

      {created ? (
        <Card>
          <CardHeader className="border-b">
            <CardTitle>Tenant provisioned</CardTitle>
            <CardDescription>Save credentials below — they are shown once.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 pt-4 text-sm">
            <p>
              <span className="font-medium text-foreground">Tenant ID:</span>{" "}
              <span className="font-mono">{created.tenant_id}</span>
            </p>
            {created.domain ? (
              <p>
                <span className="font-medium text-foreground">Domain:</span>{" "}
                <span className="font-mono">{created.domain}</span>
              </p>
            ) : null}
            {created.environment ? (
              <p>
                <span className="font-medium text-foreground">Environment:</span>{" "}
                <span className="capitalize">{created.environment}</span>
              </p>
            ) : null}
            {created.slug ? (
              <p>
                <span className="font-medium text-foreground">Slug:</span>{" "}
                <span className="font-mono">{created.slug}</span>
              </p>
            ) : null}
            {created.brand_domain ? (
              <p>
                <span className="font-medium text-foreground">Brand domain:</span>{" "}
                <span className="font-mono">{created.brand_domain}</span>
              </p>
            ) : null}
            {created.playbook_version ? (
              <p>
                <span className="font-medium text-foreground">Rollout playbook:</span>{" "}
                <span className="font-mono">v{created.playbook_version}</span>
              </p>
            ) : null}
            {created.assigned_policy_code ? (
              <p>
                <span className="font-medium text-foreground">Rollout policy:</span>{" "}
                <span className="font-mono">{created.assigned_policy_code}</span>
                <span className="text-muted-foreground"> (timeline, gates, email notifications)</span>
              </p>
            ) : null}
            {created.public_holidays_seeded ? (
              <p>
                <span className="font-medium text-foreground">PH holidays seeded:</span>{" "}
                {created.public_holidays_seeded} dates
                {created.holiday_years?.length
                  ? ` (${created.holiday_years.join(", ")})`
                  : null}
              </p>
            ) : null}
            {created.domain_endpoints && created.domain_endpoints.length > 0 ? (
              <div className="space-y-2">
                <p className="font-medium text-foreground">Recommended hostnames</p>
                <ul className="space-y-2 text-xs text-muted-foreground">
                  {created.domain_endpoints.map((endpoint) => (
                    <li
                      key={`${endpoint.purpose}-${endpoint.hostname}`}
                      className="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                    >
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium capitalize text-foreground">{endpoint.purpose}</span>
                        {endpoint.is_primary ? (
                          <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                            Primary
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 font-mono">{endpoint.hostname}</p>
                      <a
                        href={endpoint.login_url}
                        className="mt-1 inline-block text-primary underline-offset-4 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        {endpoint.login_url}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {created.initial_admin ? (
              <TenantCredentialsPanel
                initialAdmin={created.initial_admin}
                loginDomain={created.domain}
              />
            ) : null}
            <div className="flex flex-wrap gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setCreated(null);
                  slugLocked.current = false;
                  brandLocked.current = false;
                  domainLocked.current = false;
                  setHostnameCustomized(false);
                  form.reset({
                    domain: "",
                    tenant_id: "",
                    slug: "",
                    brand_domain: "",
                    environment: "local",
                    tco_sequence_prefix: "A",
                    playbook_version_id: "",
                    seed: false,
                  });
                }}
              >
                Create another
              </Button>
              <Link href="/platform" className={buttonVariants({ variant: "outline", size: "default" })}>
                Back to directory
              </Link>
            </div>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(240px,300px)] lg:items-start">
          <form className="space-y-6" noValidate onSubmit={form.handleSubmit(onSubmit)}>
            <Card>
              <CardHeader className="border-b pb-4">
                <CardTitle>Target environment</CardTitle>
                <CardDescription>
                  Choose which environment you are provisioning now. You can add the others from the tenant
                  directory later.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-4">
                <TenantEnvironmentPicker
                  value={environment}
                  onChange={handleEnvironmentChange}
                  disabled={mutation.isPending}
                />
                <div className="flex flex-wrap gap-2">
                  {domainExamples.map((example) => (
                    <button
                      key={example.label}
                      type="button"
                      className="rounded-md border border-border bg-muted/40 px-2 py-1 text-xs text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
                      onClick={() => applyQuickExample(example)}
                    >
                      {example.label}
                    </button>
                  ))}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b pb-4">
                <CardTitle>Organization identity</CardTitle>
                <CardDescription>
                  Slug and brand identify the org across all environments. Hostname is generated from your
                  selection above.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <FormInput
                    label={
                      <>
                        Slug <span className="text-destructive">*</span>
                      </>
                    }
                    placeholder="atc"
                    autoComplete="off"
                    name={slugField.name}
                    ref={slugField.ref}
                    onBlur={slugField.onBlur}
                    onChange={(event) => {
                      void slugField.onChange(event);
                      syncIdentityLocksFromValues();
                    }}
                    error={form.formState.errors.slug}
                  />
                  <FormInput
                    label="Brand domain"
                    placeholder="example.com"
                    autoComplete="off"
                    name={brandField.name}
                    ref={brandField.ref}
                    onBlur={brandField.onBlur}
                    onChange={(event) => {
                      void brandField.onChange(event);
                      syncIdentityLocksFromValues();
                    }}
                    error={form.formState.errors.brand_domain}
                  />
                </div>
                <div className="space-y-1.5">
                  <FormInput
                    label={
                      <>
                        Tenant hostname <span className="text-destructive">*</span>
                      </>
                    }
                    placeholder="atc.localhost"
                    autoComplete="off"
                    name={domainField.name}
                    ref={domainField.ref}
                    onBlur={domainField.onBlur}
                    onChange={(event) => {
                      domainLocked.current = true;
                      setHostnameCustomized(true);
                      slugLocked.current = false;
                      brandLocked.current = false;
                      void domainField.onChange(event);
                    }}
                    error={form.formState.errors.domain}
                  />
                  <div className="flex flex-wrap items-center gap-2">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-7 px-2 text-xs"
                      onClick={applyRecommendedHostname}
                    >
                      Use recommended hostname
                    </Button>
                    {hostnameCustomized ? (
                      <span className="text-xs text-muted-foreground">Custom hostname</span>
                    ) : (
                      <span className="text-xs text-muted-foreground">
                        Auto-updates when slug, brand, or environment changes
                      </span>
                    )}
                  </div>
                </div>
                <div
                  className="flex gap-2 rounded-md border border-border/80 bg-muted/30 px-3 py-2.5 text-xs text-muted-foreground"
                  role="note"
                >
                  <Info className="mt-0.5 size-3.5 shrink-0 text-primary" aria-hidden />
                  <div className="space-y-1">
                    <p>
                      <span className="font-medium text-foreground">Slug</span> identifies the org across
                      environments (required for environment switch). It is{" "}
                      <span className="font-medium text-foreground">not</span> required in brand DNS URLs.
                    </p>
                    <p>
                      Local: <span className="font-mono text-foreground">{"{slug}.localhost"}</span>. Staging /
                      production with a brand domain:{" "}
                      <span className="font-mono text-foreground">staging.example.com</span>,{" "}
                      <span className="font-mono text-foreground">app.example.com</span>.
                    </p>
                    {localhostBrandDefault ? (
                      <p>
                        Default brand for <span className="font-mono">{"{slug}"}.localhost</span>:{" "}
                        <span className="font-mono text-foreground">{localhostBrandDefault}</span>
                      </p>
                    ) : null}
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b pb-4">
                <CardTitle>Workspace modules</CardTitle>
                <CardDescription>
                  Choose which product modules this tenant can use. You can change this later from
                  the tenant directory.
                </CardDescription>
              </CardHeader>
              <CardContent className="pt-4">
                <TenantModulesPicker value={enabledModules} onChange={setEnabledModules} />
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b pb-4">
                <CardTitle>Rollout &amp; site IDs</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4 pt-4">
                <FormInput
                  label="TCO sequence prefix"
                  placeholder="A"
                  autoComplete="off"
                  {...form.register("tco_sequence_prefix")}
                  error={form.formState.errors.tco_sequence_prefix}
                />
                <div className="space-y-1.5">
                  <Label htmlFor="playbook-version">Rollout playbook</Label>
                  <Select
                    id="playbook-version"
                    className={filterSelectClassName}
                    {...form.register("playbook_version_id")}
                    disabled={playbooksQuery.isLoading}
                  >
                    <option value="">Latest published (recommended)</option>
                    {playbookVersions.map((version) => (
                      <option key={version.id} value={version.id}>
                        v{version.version} — {version.name}
                      </option>
                    ))}
                  </Select>
                  <p className="text-xs text-muted-foreground">
                    Policy bundle (gates, email notifications) is assigned from platform defaults.
                  </p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b pb-4">
                <CardTitle>Advanced</CardTitle>
                <CardDescription>Optional — leave blank unless you need a fixed tenant UUID or demo data.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-4">
                <FormInput
                  label="Tenant ID (UUID)"
                  placeholder="Auto-generated if empty"
                  autoComplete="off"
                  {...form.register("tenant_id")}
                  error={form.formState.errors.tenant_id}
                />
                <label className="flex items-start gap-3 rounded-md border border-border/70 bg-muted/20 px-3 py-3 text-sm">
                  <Checkbox
                    checked={seed}
                    onCheckedChange={(v) => form.setValue("seed", v === true, { shouldValidate: true })}
                    className="mt-0.5 size-4"
                  />
                  <span>
                    <span className="font-medium text-foreground">Seed demo dataset</span>
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                      Dev/UAT only — sample sites, rollouts, and users. Adds provisioning time.
                    </span>
                  </span>
                </label>
              </CardContent>
            </Card>

            <div className="lg:hidden">
              <CreateTenantHostPreview
                slug={slug ?? ""}
                brandDomain={brandDomain ?? ""}
                selectedEnvironment={environment}
                hostname={domain ?? ""}
              />
            </div>

            <div className="sticky bottom-0 z-10 -mx-1 rounded-xl border border-border bg-card/95 p-4 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-card/80">
              <Button className="w-full" type="submit" disabled={mutation.isPending}>
                {mutation.isPending
                  ? "Creating tenant… (may take 1–2 min)"
                  : `Create ${environment} tenant`}
              </Button>
              {mutation.isPending ? (
                <p className="mt-2 text-center text-xs text-muted-foreground">
                  Provisioning database, migrations, rollout playbook, and admin user. Keep this tab open.
                </p>
              ) : (
                <p className="mt-2 text-center text-xs text-muted-foreground">
                  First-time provisioning on Windows can take up to two minutes.
                </p>
              )}
            </div>
          </form>

          <aside className="hidden lg:block lg:sticky lg:top-20">
            <CreateTenantHostPreview
              slug={slug ?? ""}
              brandDomain={brandDomain ?? ""}
              selectedEnvironment={environment}
              hostname={domain ?? ""}
            />
          </aside>
        </div>
      )}

      <p className="mt-8 text-center text-xs text-muted-foreground">
        <Link href="/platform" className="text-primary underline-offset-4 hover:underline">
          Tenant directory
        </Link>
        {" · "}
        <Link href="/login" className="text-primary underline-offset-4 hover:underline">
          Tenant sign-in
        </Link>
      </p>
    </div>
  );
}
