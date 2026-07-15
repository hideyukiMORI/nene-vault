import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { UpdateVaultSettingsInput, VaultSettings } from './api-types';

const QUERY_KEY = ['vault-settings'] as const;

export function useVaultSettings(): UseQueryResult<VaultSettings, AppError> {
  return useQuery<VaultSettings, AppError>({
    queryKey: QUERY_KEY,
    queryFn: ({ signal }) => apiClient.get<VaultSettings>('/admin/vault/settings', signal),
  });
}

export function useUpdateVaultSettings(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<VaultSettings, AppError, UpdateVaultSettingsInput>({
    mutationFn: (body) => apiClient.patch<VaultSettings>('/admin/vault/settings', body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: QUERY_KEY });
      onSuccess?.();
    },
  });
}
