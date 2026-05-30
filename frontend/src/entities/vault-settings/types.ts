export interface VaultSettings {
  organization_id: number;
  retention_years: number;
  storage_path_override: string | null;
  invoice_api_base_url: string | null;
  clear_api_base_url: string | null;
  updated_at: string | null;
}

export interface UpdateVaultSettingsInput {
  retention_years?: number | undefined;
  storage_path_override?: string | null | undefined;
  invoice_api_base_url?: string | null | undefined;
  clear_api_base_url?: string | null | undefined;
}
