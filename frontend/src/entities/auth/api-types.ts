// Auth DTOs. Post-codegen these can alias from shared/api/generated; kept explicit
// here so the slice compiles before the first `npm run codegen` run.

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  expires_at: string;
  user_id: number;
  email: string;
  role: string;
  org_id: number | null;
}
