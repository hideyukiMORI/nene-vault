import { useMutation, type UseMutationResult } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { LoginRequest, LoginResponse } from './api-types';
import { authStore, type AuthSession } from './model';

function toSession(response: LoginResponse): AuthSession {
  return {
    token: response.token,
    userId: response.user_id,
    email: response.email,
    role: response.role,
    orgId: response.org_id,
  };
}

/** Logs in, persists the session, and returns the session on success. */
export function useLogin(): UseMutationResult<AuthSession, AppError, LoginRequest> {
  return useMutation<AuthSession, AppError, LoginRequest>({
    mutationFn: async (input) => {
      const response = await apiClient.post<LoginResponse>('/admin/auth/login', input);
      const session = toSession(response);
      authStore.setSession(session);
      return session;
    },
  });
}
