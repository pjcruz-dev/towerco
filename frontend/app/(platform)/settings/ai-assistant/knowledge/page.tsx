import { redirect } from "next/navigation";

/** Knowledge base UI removed — assistant uses live AI + tools instead. */
export default function AssistantKnowledgePage() {
  redirect("/dashboard");
}
