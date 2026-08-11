export type OperationalAcronym = {
  id: string;
  acronym: string;
  definition: string;
  category: string | null;
  sort_order: number;
  is_active: boolean;
  updated_at?: string | null;
};

export type OperationalAcronymMap = Record<string, OperationalAcronym>;
