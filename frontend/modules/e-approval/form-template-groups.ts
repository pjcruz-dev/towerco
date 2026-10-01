import type { EApprovalFormTemplate } from "@/modules/e-approval/types";

export const FORM_TEMPLATE_GROUP_ORDER = ["finance", "hr", "documents", "general"] as const;

export type FormTemplateGroupId = (typeof FORM_TEMPLATE_GROUP_ORDER)[number];

export type FormTemplateGroup = {
  id: FormTemplateGroupId;
  label: string;
  description: string;
  templates: EApprovalFormTemplate[];
};

const GROUP_COPY: Record<FormTemplateGroupId, { label: string; description: string }> = {
  finance: {
    label: "Finance",
    description: "Cash advance, liquidation, and reimbursement.",
  },
  hr: {
    label: "People & HR",
    description: "Leave and onboarding.",
  },
  documents: {
    label: "Documents",
    description: "Site binder and controlled-document reviews.",
  },
  general: {
    label: "Other",
    description: "Tenant templates and uncategorized forms.",
  },
};

const GROUP_BY_TEMPLATE_ID: Record<string, FormTemplateGroupId> = {
  cash_advance: "finance",
  liquidation: "finance",
  reimbursement: "finance",
  leave_request: "hr",
  employee_onboarding: "hr",
  site_document_review: "documents",
};

const CATEGORY_TO_GROUP: Record<string, FormTemplateGroupId> = {
  finance: "finance",
  hr: "hr",
  documents: "documents",
  general: "general",
};

export function formTemplateGroupId(template: Pick<EApprovalFormTemplate, "id" | "category">): FormTemplateGroupId {
  const fromId = GROUP_BY_TEMPLATE_ID[template.id];
  if (fromId) {
    return fromId;
  }

  const fromCategory = CATEGORY_TO_GROUP[template.category.trim().toLowerCase()];
  return fromCategory ?? "general";
}

export function groupFormTemplates(templates: EApprovalFormTemplate[]): FormTemplateGroup[] {
  const buckets = new Map<FormTemplateGroupId, EApprovalFormTemplate[]>();
  for (const id of FORM_TEMPLATE_GROUP_ORDER) {
    buckets.set(id, []);
  }

  for (const template of templates) {
    const groupId = formTemplateGroupId(template);
    buckets.get(groupId)?.push(template);
  }

  return FORM_TEMPLATE_GROUP_ORDER.flatMap((id) => {
    const items = buckets.get(id) ?? [];
    if (items.length === 0) {
      return [];
    }

    return [
      {
        id,
        label: GROUP_COPY[id].label,
        description: GROUP_COPY[id].description,
        templates: items,
      },
    ];
  });
}
