import { redirect } from "next/navigation";

/** Parent segment has no list UI; avoid Next.js RSC 404 for `/e-approval/attachments`. */
export default function EApprovalAttachmentsIndexPage() {
  redirect("/e-approval/submissions");
}
