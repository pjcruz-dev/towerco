export type AssetPerson = {
  id: string;
  name: string;
  email: string;
  department: string | null;
  job_title: string | null;
  given_name?: string | null;
  surname?: string | null;
};

export type AssetLifecycle = {
  months: number | null;
  band: string | null;
};

export type AssetRecord = {
  id: string;
  asset_tag: string;
  serial_number: string;
  asset_type: string;
  status: string;
  manufacturer: string | null;
  product_name: string | null;
  product_version: string | null;
  hostname: string | null;
  old_hostname: string | null;
  processor: string | null;
  installed_ram: string | null;
  operating_system: string | null;
  system_type: string | null;
  bios_version: string | null;
  purchase_date: string | null;
  warranty_end: string | null;
  commission_date: string | null;
  notes: string | null;
  custom_values: Record<string, unknown>;
  created_at?: string | null;
  updated_at?: string | null;
  created_by_name?: string | null;
  updated_by_name?: string | null;
  lifecycle: AssetLifecycle;
  qr_path: string;
  assignee: AssetPerson | null;
  accountability: {
    id: string;
    number: string;
    status: string;
    employee_id: string;
    employee_name?: string | null;
    items?: Array<{
      quantity: string;
      item: string;
      brand: string;
      serial_number: string;
      asset_tag?: string;
    }>;
  } | null;
  assignments: Array<{
    id: string;
    status: string;
    assigned_at: string | null;
    returned_at: string | null;
    employee: AssetPerson | null;
    notes: string | null;
  }>;
  events: Array<{
    id: string;
    event_type: string;
    from_place: string | null;
    to_place: string | null;
    occurred_at: string | null;
    payload: Record<string, unknown> | null;
  }>;
  network_adapters: Array<{
    id: string;
    adapter_name: string | null;
    adapter_type: string | null;
    mac_address: string | null;
    ip_address: string | null;
    is_active: boolean;
  }>;
  disks: Array<{ id: string; model: string | null; serial_number: string | null; size_label: string | null }>;
  video_cards: Array<{ id: string; name: string | null; driver_version: string | null }>;
  applications: Array<{ id: string; application_name: string; version: string | null; publisher: string | null }>;
  repairs: Array<{
    id: string;
    status: string;
    usable: boolean | null;
    problem: string | null;
    result: string | null;
    vendor_email_subject: string | null;
    vendor_email_body: string | null;
  }>;
  hostname_history: Array<{
    old_hostname: string | null;
    new_hostname: string;
    reason: string | null;
    changed_at: string | null;
  }>;
};

export type AssetListMeta = {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
};

export type AssetDashboardCard = {
  key: string;
  label: string;
  value: number;
  href: string;
};

export type AssetSchemaField = {
  id: string;
  name: string;
  label: string;
  type: string;
  is_system_field: boolean;
  show_in_table: boolean;
  field_order: number;
};

export type OffboardingCase = {
  id: string;
  number: string;
  status: string;
  employee_id: string;
  last_working_day: string | null;
  items: Array<{
    id: string;
    asset_id: string | null;
    result: string | null;
    condition: string | null;
    asset?: { asset_tag?: string; serial_number?: string; product_name?: string } | null;
  }>;
};
