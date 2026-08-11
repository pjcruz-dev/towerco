import { redirect } from "next/navigation";

type Props = { params: Promise<{ formId: string }> };

export default async function ApprovalFocusAliasPage({ params }: Props) {
  const { formId } = await params;
  redirect(`/e-approval/focus/${formId}`);
}
