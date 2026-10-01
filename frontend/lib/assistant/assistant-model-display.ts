export function modelLabel(model: string): string {
  const id = model.replace(/^models\//, "");
  const known: Record<string, string> = {
    "gemini-3.8-flash": "3.8 Flash",
    "gemini-3.7-flash": "3.7 Flash",
    "gemini-3.6-flash": "3.6 Flash",
    "gemini-3.5-flash": "3.5 Flash",
    "gemini-3.5-flash-lite": "3.5 Flash-Lite",
    "gemini-3.1-pro-preview": "3.1 Pro",
    "gemini-3.1-flash-lite": "3.1 Flash-Lite",
    "gemini-3-flash-preview": "3 Flash",
    "gemini-2.5-pro": "2.5 Pro",
    "gemini-2.5-flash": "2.5 Flash",
    "gemini-2.5-flash-lite": "2.5 Flash-Lite",
    "gemini-flash-latest": "Flash (latest)",
    "gemini-pro-latest": "Pro (latest)",
  };
  if (known[id]) return known[id];

  return id
    .replace(/-/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function providerDisplayName(provider: string | null | undefined): string | null {
  const label = (provider ?? "").trim().toLowerCase();
  if (label === "cursor" || label === "cursor_ai") return "Cursor";
  if (label === "openai" || label === "chatgpt") return "OpenAI";
  if (label === "bedrock") return "Bedrock";
  if (label === "gemini" || label === "google" || label === "google_ai") return "Gemini";
  if (label === "local" || label === "fake") return "Local";
  return label !== "" ? label : null;
}

export function formatResetsIn(seconds: number): string {
  if (seconds <= 0) return "";
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  if (m <= 0) return `${s}s`;
  return `${m}:${String(s).padStart(2, "0")}`;
}
