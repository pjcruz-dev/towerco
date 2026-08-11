import { redirect } from "next/navigation";

import { EApprovalFormEditPageClient } from "../e-approval-form-edit-page-client";

type Props = { params: Promise<{ id: string }> };

const RESERVED_FORM_SLUGS = new Set(["new", "create"]);

export default async function EApprovalFormEditPage({ params }: Props) {
  const { id } = await params;

  if (RESERVED_FORM_SLUGS.has(id.toLowerCase())) {
    redirect("/e-approval/forms/create");
  }

  return <EApprovalFormEditPageClient formId={id} />;
}
