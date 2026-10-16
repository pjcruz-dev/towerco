"use client";

import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";

import { PrintDocumentFrame } from "@/components/dynamic-entities/print-document-frame";
import { fetchAsset, fetchAssetSchema } from "@/lib/api/modules/assets-api";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import { fetchDynEntity } from "@/lib/api/modules/dynamic-entities-api";
import { getErrorMessage } from "@/lib/api/error";
import { buildSystemDateTokens, renderDynPrintCss, renderDynPrintTemplate } from "@/lib/dynamic-entities/dyn-print-template";
import { resolvePrintTemplate } from "@/lib/dynamic-entities/dyn-print-templates";
import type { AssetRecord } from "@/modules/assets/types";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

function typeLabel(type: string): string {
  const labels: Record<string, string> = {
    laptop: "Laptop",
    desktop: "Desktop",
    monitor: "Monitor",
    dock: "Dock",
    phone: "Phone",
    peripheral: "Peripheral",
  };
  return labels[type.toLowerCase()] ?? (type ? type.charAt(0).toUpperCase() + type.slice(1).toLowerCase() : "Item");
}

type PrintLine = {
  quantity: string;
  item: string;
  brand: string;
  serial_number: string;
  asset_tag: string;
  result: string;
  condition: string;
};

function recordLine(record: AssetRecord): PrintLine {
  return {
    quantity: "1",
    item: record.product_name?.trim() || typeLabel(record.asset_type),
    brand: record.manufacturer ?? "",
    serial_number: record.serial_number,
    asset_tag: record.asset_tag,
    result: "",
    condition: "",
  };
}

function sameLine(left: PrintLine, right: PrintLine): boolean {
  const tag = left.asset_tag.trim().toLowerCase();
  const otherTag = right.asset_tag.trim().toLowerCase();
  if (tag && otherTag && tag === otherTag) return true;
  const serial = left.serial_number.trim().toLowerCase();
  const otherSerial = right.serial_number.trim().toLowerCase();
  if (serial && otherSerial && serial === otherSerial) return true;
  return (
    !tag &&
    !otherTag &&
    !serial &&
    !otherSerial &&
    left.item.trim().toLowerCase() === right.item.trim().toLowerCase()
  );
}

/** One row per asset, plus extras on the accountability such as a mouse or charger. */
function collectLines(records: AssetRecord[]): PrintLine[] {
  const lines: PrintLine[] = [];
  const push = (line: PrintLine) => {
    if (lines.some((existing) => sameLine(existing, line))) return;
    lines.push(line);
  };
  for (const record of records) {
    push(recordLine(record));
    for (const item of record.accountability?.items ?? []) {
      push({
        quantity: item.quantity || "1",
        item: item.item,
        brand: item.brand,
        serial_number: item.serial_number,
        asset_tag: item.asset_tag ?? "",
        result: "",
        condition: "",
      });
    }
  }
  return lines;
}

export function AssetPrintClient({ tag }: { tag: string }) {
  const branding = useTenantBrandingStore((state) => state.branding);
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const granted =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId)?.permissions ??
    user?.permissions ??
    [];
  const canEditTemplate = granted.includes(permissions.printablesManage);
  const searchParams = useSearchParams();
  const templateId = searchParams.get("template");
  const tags = [tag, ...(searchParams.get("with") ?? "").split(",")]
    .map((item) => item.trim())
    .filter((item, index, all) => item !== "" && all.indexOf(item) === index)
    .slice(0, 50);
  const asset = useQuery({
    queryKey: ["asset-print", tags.join(",")],
    queryFn: async () => {
      await fetchAssetSchema();
      const [records, entity] = await Promise.all([
        Promise.all(tags.map((item) => fetchAsset(item))),
        fetchDynEntity("it_asset"),
      ]);
      return { records, entity };
    },
  });

  if (asset.isLoading) {
    return <p className="p-8 text-sm">Loading asset…</p>;
  }
  if (asset.isError || !asset.data) {
    return <p className="p-8 text-sm">{getErrorMessage(asset.error)}</p>;
  }

  const { records, entity } = asset.data;
  const print = resolvePrintTemplate(entity.print_settings, templateId, entity.name);
  const templateHtml = print.template_html?.trim() ?? "";
  const logoUrl = resolveBrandingAssetUrl(branding?.logo_url) ?? "";
  const first = records[0];
  const lines = collectLines(records);
  const assigneeName =
    records
      .map((record) => record.accountability?.employee_name || record.assignee?.name || "")
      .find((name) => name.trim() !== "") ?? "";
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
      ...(first ?? {}),
      assignee_name: assigneeName,
      asset_type_label: first ? typeLabel(first.asset_type) : "",
      number: first?.accountability?.number ?? first?.asset_tag ?? tag,
    },
    items: lines,
  };
  const html = templateHtml ? renderDynPrintTemplate(templateHtml, ctx) : "";
  const css = print.template_css?.trim() ? renderDynPrintCss(print.template_css, ctx) : "";

  return (
    <PrintDocumentFrame
      backHref={first ? `/assets/${first.asset_tag}` : "/assets"}
      editTemplateHref={
        canEditTemplate
          ? `/dynamic-entities/printables/edit?entity=it_asset&template=${encodeURIComponent(print.id)}`
          : null
      }
      listLabel={
        lines.length > 1
          ? `${lines.length} items · ${print.name || print.list_label}`
          : `${print.name || print.list_label} · ${first?.asset_tag ?? tag}`
      }
      defaultPaper="letter"
    >
      {css ? <style>{css}</style> : null}
      {html ? (
        <div dangerouslySetInnerHTML={{ __html: html }} />
      ) : (
        <div>
          {lines.map((line) => (
            <p key={`${line.asset_tag}-${line.item}-${line.serial_number}`}>
              {line.quantity} · {line.item} · {line.brand} · {line.serial_number}
            </p>
          ))}
        </div>
      )}
    </PrintDocumentFrame>
  );
}
