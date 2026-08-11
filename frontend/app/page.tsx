import { headers } from "next/headers";
import { redirect } from "next/navigation";

const CENTRAL_HOSTS = new Set(["localhost", "127.0.0.1"]);

export default async function Home() {
  const host = (await headers()).get("host")?.split(":")[0]?.toLowerCase() ?? "";

  if (CENTRAL_HOSTS.has(host)) {
    redirect("/platform/login");
  }

  redirect("/dashboard");
}
