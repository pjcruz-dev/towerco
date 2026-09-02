import axios from "axios";

import type { PublicAppMenuPayload } from "@/lib/api/modules/platform-api";

const publicClient = axios.create({
  baseURL:
    process.env.NEXT_PUBLIC_CENTRAL_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "http://localhost:8000/api/v1",
  timeout: 20_000,
  headers: {
    Accept: "application/json",
  },
});

export async function fetchPublicAppMenu(): Promise<PublicAppMenuPayload> {
  const response = await publicClient.get<{ data: PublicAppMenuPayload }>("/public/app-menu");
  return {
    settings: response.data.data?.settings ?? { grid_columns: 4 },
    groups: response.data.data?.groups ?? [],
    ungrouped: response.data.data?.ungrouped ?? [],
  };
}
