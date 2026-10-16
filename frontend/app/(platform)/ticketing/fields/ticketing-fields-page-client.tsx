"use client";

import { DynamicFieldsAdminPageClient } from "@/app/(platform)/dynamic-entities/fields/dynamic-fields-admin-page-client";

export function TicketingFieldsPageClient() {
  return <DynamicFieldsAdminPageClient lockedSlug="helpdesk_ticket" />;
}
