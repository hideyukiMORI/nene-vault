import { api, setToken } from './client';

export interface LoginResponse {
  token: string;
  expires_at: string;
  user_id: number;
  email: string;
  role: string;
  org_id: number | null;
}

export async function login(email: string, password: string): Promise<LoginResponse> {
  const result = await api.post<LoginResponse>('/admin/auth/login', { email, password });
  setToken(result.token);
  return result;
}
