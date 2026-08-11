export type RaiseTicketLinkInput = {
  link_module: string;
  link_type: string;
  link_id: string;
  link_label?: string;
};

export type RaiseTicketPrefill = {
  title: string;
  description?: string;
  source_module: string;
  source_reference_type: string;
  source_reference_id: string;
  source_label?: string;
  category?: string;
  links?: RaiseTicketLinkInput[];
};

export function buildRaiseTicketUrl(prefill: RaiseTicketPrefill): string {
  const params = new URLSearchParams();
  params.set("source_module", prefill.source_module);
  params.set("source_reference_type", prefill.source_reference_type);
  params.set("source_reference_id", prefill.source_reference_id);
  if (prefill.source_label) params.set("source_label", prefill.source_label);
  params.set("title", prefill.title);
  if (prefill.description) params.set("description", prefill.description);
  if (prefill.category) params.set("category", prefill.category);
  (prefill.links ?? []).forEach((link, index) => {
    if (!link.link_id) return;
    // Index 0 keeps the legacy (unindexed) keys for backward compatibility; extra
    // links (e.g. the parent PO of a GRN) are serialized with an index suffix.
    const suffix = index === 0 ? "" : `_${index}`;
    params.set(`link_module${suffix}`, link.link_module);
    params.set(`link_type${suffix}`, link.link_type);
    params.set(`link_id${suffix}`, link.link_id);
    if (link.link_label) params.set(`link_label${suffix}`, link.link_label);
  });
  return `/ticketing/tickets/new?${params.toString()}`;
}

export function parseRaiseTicketSearchParams(searchParams: URLSearchParams): Partial<RaiseTicketPrefill> {
  const sourceModule = searchParams.get("source_module");
  const sourceReferenceType = searchParams.get("source_reference_type");
  const sourceReferenceId = searchParams.get("source_reference_id");
  const title = searchParams.get("title");

  if (!sourceModule || !sourceReferenceType || !sourceReferenceId || !title) {
    return {};
  }

  const prefill: Partial<RaiseTicketPrefill> = {
    title,
    description: searchParams.get("description") ?? undefined,
    source_module: sourceModule,
    source_reference_type: sourceReferenceType,
    source_reference_id: sourceReferenceId,
    source_label: searchParams.get("source_label") ?? undefined,
    category: searchParams.get("category") ?? undefined,
  };

  const links: RaiseTicketLinkInput[] = [];
  for (let index = 0; index < 10; index += 1) {
    const suffix = index === 0 ? "" : `_${index}`;
    const linkModule = searchParams.get(`link_module${suffix}`);
    const linkType = searchParams.get(`link_type${suffix}`);
    const linkId = searchParams.get(`link_id${suffix}`);
    if (!linkModule || !linkType || !linkId) {
      if (index === 0) continue;
      break;
    }
    links.push({
      link_module: linkModule,
      link_type: linkType,
      link_id: linkId,
      link_label: searchParams.get(`link_label${suffix}`) ?? undefined,
    });
  }
  if (links.length > 0) {
    prefill.links = links;
  }

  return prefill;
}
