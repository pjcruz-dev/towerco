"use client";

import { useQuery } from "@tanstack/react-query";

import { PrintDocumentFrame } from "@/components/dynamic-entities/print-document-frame";
import { fetchOffboarding } from "@/lib/api/modules/assets-api";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { fetchDynEntity } from "@/lib/api/modules/dynamic-entities-api";
import { getErrorMessage } from "@/lib/api/error";
import { buildSystemDateTokens, renderDynPrintCss, renderDynPrintTemplate } from "@/lib/dynamic-entities/dyn-print-template";
import { resolvePrintTemplate } from "@/lib/dynamic-entities/dyn-print-templates";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

const RETURN_TEMPLATE_ID = "it-asset-return-checklist";

export function OffboardingPrintClient({ id }: { id: string }) {
  const branding = useTenantBrandingStore((state) => state.branding);
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const granted =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId)?.permissions ??
    user?.permissions ??
    [];
  const canEditTemplate = granted.includes(permissions.printablesManage);
  const current = useQuery({
    queryKey: ["offboarding-print", id],
    queryFn: async () => {
      const [sheet, entity] = await Promise.all([fetchOffboarding(id), fetchDynEntity("it_asset")]);
      return { sheet, entity };
    },
  });

  if (current.isLoading) {
    return <p className="p-8 text-sm">Loading checklist…</p>;
  }
  if (current.isError || !current.data) {
    return <p className="p-8 text-sm">{getErrorMessage(current.error)}</p>;
  }

  const { sheet, entity } = current.data;
  const print = resolvePrintTemplate(entity.print_settings, RETURN_TEMPLATE_ID, entity.name);
  const templateHtml = print.template_html?.trim() ?? "";
  const logoUrl = resolveBrandingAssetUrl(branding?.logo_url) ?? "";
  const ctx = {
    system: {
      ...buildSystemDateTokens(),
      company_name: print.company_name?.trim() || branding?.organization_label?.trim() || "Alliance Towers Corporation",
      company_address: branding?.company_address?.trim() || "",
      company_phone: branding?.company_phone?.trim() || "",
      company_email: branding?.company_email?.trim() || "",
      company_tin: branding?.company_tin?.trim() || "",
      company_logo: logoUrl,
      company_logo_path: logoUrl,
    },
    theme: { accent: "#1d4e89" },
    record: {
      number: sheet.number,
      assignee_name: sheet.employee_id,
      status: sheet.status,
    },
    items: sheet.items.map((item) => ({
      asset_tag: item.asset?.asset_tag ?? "",
      item: item.asset?.product_name ?? "",
      serial_number: item.asset?.serial_number ?? "",
      result: item.result ?? "",
      condition: item.condition ?? "",
    })),
  };
  const html = templateHtml ? renderDynPrintTemplate(templateHtml, ctx) : "";
  const css = print.template_css?.trim() ? renderDynPrintCss(print.template_css, ctx) : "";

  return (
    <PrintDocumentFrame
      backHref="/assets/offboarding"
      editTemplateHref={
        canEditTemplate
          ? `/dynamic-entities/printables/edit?entity=it_asset&template=${encodeURIComponent(print.id)}`
          : null
      }
      listLabel={`${print.name || "Return Checklist"} · ${sheet.number}`}
      defaultPaper="letter"
    >
      {css ? <style>{css}</style> : null}
      {html ? (
        <div dangerouslySetInnerHTML={{ __html: html }} />
      ) : (
        <main className="mx-auto max-w-3xl space-y-6 bg-white p-8 text-black">
          <header>
            <h1 className="text-2xl font-semibold">Asset return checklist</h1>
            <p className="text-sm">{sheet.number}</p>
          </header>
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr>
                <th className="border border-black px-2 py-1 text-left">Tag</th>
                <th className="border border-black px-2 py-1 text-left">Result</th>
                <th className="border border-black px-2 py-1 text-left">Condition</th>
              </tr>
            </thead>
            <tbody>
              {sheet.items.map((item) => (
                <tr key={item.id}>
                  <td className="border border-black px-2 py-1 font-mono">{item.asset?.asset_tag ?? "—"}</td>
                  <td className="border border-black px-2 py-1">{item.result ?? ""}</td>
                  <td className="border border-black px-2 py-1">{item.condition ?? ""}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </main>
      )}
    </PrintDocumentFrame>
  );
}
