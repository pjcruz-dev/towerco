import { redirect } from "next/navigation";

export default function EApprovalRequestIndexPage() {
  redirect("/e-approval/submissions/new");
}
