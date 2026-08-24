import axios from "axios";

const GENERIC_VALIDATION_MESSAGES = new Set([
  "The given data was invalid.",
  "Validation failed.",
]);

export function getApiFieldErrors(error: unknown, prefixToStrip = ""): Record<string, string> {
  if (!axios.isAxiosError(error)) {
    return {};
  }

  const fieldErrors =
    (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {};
  const map: Record<string, string> = {};

  for (const [key, messages] of Object.entries(fieldErrors)) {
    const fieldName =
      prefixToStrip !== "" && key.startsWith(prefixToStrip)
        ? key.slice(prefixToStrip.length)
        : key;
    const message = messages[0];
    if (message) {
      map[fieldName] = message;
    }
  }

  return map;
}

export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;

    const fieldErrors = data?.errors ?? {};
    const preferredFields = [
      "parent_submission_id",
      "total_reimbursement",
      "name",
      "fields",
      "steps",
      "tenant",
      "confirmation",
      "environment",
      "domain",
      "email",
      "password",
      "ticketing",
      "ticket",
    ];
    for (const field of preferredFields) {
      const message = fieldErrors[field]?.[0];
      if (message) {
        return message;
      }
    }

    const firstField = Object.keys(fieldErrors)[0];
    const firstMsg = firstField ? fieldErrors[firstField]?.[0] : undefined;
    if (firstMsg) {
      return firstMsg;
    }

    const apiMessage = data?.message?.trim();
    const status = error.response?.status;
    if (status !== undefined && status >= 500) {
      return apiMessage && !GENERIC_VALIDATION_MESSAGES.has(apiMessage)
        ? apiMessage
        : "The API returned an internal error. Try again. If it keeps failing, check the Laravel log.";
    }

    if (apiMessage && !GENERIC_VALIDATION_MESSAGES.has(apiMessage)) {
      if (apiMessage === "Tenant domain not found.") {
        const host =
          typeof window !== "undefined" && window.location.hostname
            ? window.location.hostname
            : "your-tenant-host";
        return `Tenant domain not found for "${host}". Create the tenant in Platform → Tenants with this hostname, or sign in on the URL shown after provisioning.`;
      }
      if (apiMessage === "Tenant not found.") {
        return "Tenant not found. Sign out, then sign in again on your tenant URL (the hostname from Platform → Create tenant, e.g. http://{slug}.localhost/login).";
      }
      return apiMessage;
    }

    if (error.code === "ECONNABORTED" || error.message.toLowerCase().includes("timeout")) {
      if (process.env.NEXT_PUBLIC_APP_ENV === "local") {
        return "The API did not respond in time. If you just restarted Docker or saved a large form, wait a few seconds and try again. Confirm the API is running at http://localhost:8000.";
      }
      return "The request timed out. If you were submitting a form, open Submissions — your request may already have been saved.";
    }

    if (error.code === "ERR_NETWORK" || error.message === "Network Error") {
      return "Could not reach the API. Confirm the API is running, then check Submissions in case your request was already saved.";
    }

    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Unexpected error";
}
