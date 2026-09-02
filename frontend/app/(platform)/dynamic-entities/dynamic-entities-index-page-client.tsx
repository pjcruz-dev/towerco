"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchDynEntities, type DynEntitySummary } from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";

export function DynamicEntitiesIndexPageClient() {
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchDynEntities()
      .then((rows) => {
        if (!cancelled) {
          setEntities(rows);
          setError(null);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError("Unable to load entities.");
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const packs = Array.from(new Set(entities.map((e) => e.module_pack)));

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Dynamic Entities</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              ATC module packs with fully dynamic fields — records, lists, and Manage Fields.
            </p>
          </div>
          <Link
            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
            href="/dynamic-entities/fields"
          >
            Manage Fields
          </Link>
        </header>

        {loading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        {!loading && !error
          ? packs.map((pack) => (
              <section key={pack} className="space-y-3">
                <h2 className="text-xl font-semibold text-foreground capitalize">{pack}</h2>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                  {entities
                    .filter((e) => e.module_pack === pack)
                    .map((entity) => (
                      <Card key={entity.id} className="rounded-xl border border-border/80 shadow-sm">
                        <CardHeader className="pb-2">
                          <div className="flex items-start justify-between gap-2">
                            <CardTitle className="text-base font-medium">{entity.name}</CardTitle>
                            <Badge variant="secondary">{entity.slug}</Badge>
                          </div>
                          <CardDescription className="line-clamp-2">
                            {entity.description || "No description"}
                          </CardDescription>
                        </CardHeader>
                        <CardContent>
                          <Link
                            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                            href={`/dynamic-entities/${entity.slug}`}
                          >
                            Open records
                          </Link>
                        </CardContent>
                      </Card>
                    ))}
                </div>
              </section>
            ))
          : null}

        {!loading && !error && entities.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            No entities yet. Create one via API or run{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs">php artisan atc:import-meta</code>.
          </p>
        ) : null}
      </div>
    </PermissionGate>
  );
}
