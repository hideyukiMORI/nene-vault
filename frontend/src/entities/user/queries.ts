import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { CreateUserInput, UpdateUserInput, User, UserListResponse } from './types';

const userQueryKeys = {
  all: ['users'] as const,
  list: (limit: number, offset: number) => ['users', 'list', limit, offset] as const,
  detail: (id: number) => ['users', 'detail', id] as const,
} as const;

export function useUsers(
  limit: number,
  offset: number,
): UseQueryResult<UserListResponse, AppError> {
  return useQuery<UserListResponse, AppError>({
    queryKey: userQueryKeys.list(limit, offset),
    queryFn: ({ signal }) =>
      apiClient.get<UserListResponse>(
        `/admin/users?limit=${String(limit)}&offset=${String(offset)}`,
        signal,
      ),
  });
}

export function useCreateUser(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<User, AppError, CreateUserInput>({
    mutationFn: (body) => apiClient.post<User>('/admin/users', body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: userQueryKeys.all });
      onSuccess?.();
    },
  });
}

export function useUpdateUser(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<User, AppError, { id: number } & UpdateUserInput>({
    mutationFn: ({ id, ...body }) => apiClient.patch<User>(`/admin/users/${String(id)}`, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: userQueryKeys.all });
      onSuccess?.();
    },
  });
}

export function useDeleteUser(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<undefined, AppError, number>({
    mutationFn: (id) => apiClient.delete<undefined>(`/admin/users/${String(id)}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: userQueryKeys.all });
      onSuccess?.();
    },
  });
}

export { userQueryKeys };
