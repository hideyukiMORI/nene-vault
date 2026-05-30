export type UserRole = 'superadmin' | 'admin' | 'member' | 'viewer';
export type UserStatus = 'active' | 'invited';

export interface User {
  id: number;
  email: string;
  role: UserRole;
  organization_id: number | null;
  status: UserStatus;
  created_at: string | undefined;
  updated_at: string | undefined;
}

export interface UserListResponse {
  items: User[];
  total: number;
  limit: number;
  offset: number;
}

export interface CreateUserInput {
  email: string;
  password: string;
  role: UserRole;
}

export interface UpdateUserInput {
  email?: string | undefined;
  role?: UserRole | undefined;
  status?: UserStatus | undefined;
}
