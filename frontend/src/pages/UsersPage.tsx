import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authStore } from '@/entities/auth';
import { useUsers, useCreateUser, useDeleteUser } from '@/entities/user';
import type { User } from '@/entities/user';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell, Button, Input, Stack, Text } from '@/shared/ui';
import { Pagination } from '@/features/document-search';

const PAGE_SIZE = 20;

const ROLES = ['admin', 'member', 'viewer'] as const;

const createUserSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
  role: z.enum(['admin', 'member', 'viewer']),
});

type CreateUserFormValues = z.infer<typeof createUserSchema>;

function UserFormModal({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const mutation = useCreateUser(onClose);
  const form = useForm<CreateUserFormValues>({
    resolver: zodResolver(createUserSchema),
    defaultValues: { email: '', password: '', role: 'member' },
  });
  const {
    register,
    formState: { errors },
  } = form;
  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="w-full max-w-md rounded-xl border border-border bg-surface shadow-lg">
        <div className="flex items-center justify-between border-b border-border px-inline-lg py-stack-md">
          <Text as="h2" className="text-heading-sm">
            {t('user.form.create_title')}
          </Text>
          <button type="button" onClick={onClose} className="text-muted hover:text-foreground">
            ✕
          </button>
        </div>
        <form
          onSubmit={(e) => {
            void form.handleSubmit((values) => {
              mutation.mutate(values);
            })(e);
          }}
          className="p-inline-lg"
        >
          <Stack gap="md">
            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('user.form.email_label')}</label>
              <Input
                type="email"
                placeholder={t('user.form.email_placeholder')}
                {...register('email')}
              />
              {errors.email !== undefined && (
                <Text tone="danger" className="text-label-xs">
                  {t('common.required_marker')}
                </Text>
              )}
            </div>
            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('user.form.password_label')}</label>
              <Input
                type="password"
                placeholder={t('user.form.password_placeholder')}
                {...register('password')}
              />
              {errors.password !== undefined && (
                <Text tone="danger" className="text-label-xs">
                  {t('common.required_marker')}
                </Text>
              )}
            </div>
            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('user.form.role_label')}</label>
              <select
                {...register('role')}
                className="h-10 rounded-md border border-border bg-surface px-inline-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-brand"
              >
                {ROLES.map((r) => (
                  <option key={r} value={r}>
                    {t(`user.role.${r}`)}
                  </option>
                ))}
              </select>
            </div>
            {submitError !== null && (
              <Text tone="danger" className="text-body-sm">
                {t(submitError)}
              </Text>
            )}
            <div className="flex justify-end gap-inline-md">
              <Button
                type="button"
                variant="secondary"
                onClick={onClose}
                disabled={mutation.isPending}
              >
                {t('common.buttons.cancel')}
              </Button>
              <Button type="submit" variant="primary" disabled={mutation.isPending}>
                {mutation.isPending ? t('common.status.saving') : t('common.buttons.invite')}
              </Button>
            </div>
          </Stack>
        </form>
      </div>
    </div>
  );
}

function UserRow({
  user,
  currentUserId,
  onDelete,
}: {
  user: User;
  currentUserId: number | null;
  onDelete: (id: number, email: string) => void;
}) {
  const { t } = useTranslation();
  return (
    <tr className="border-b border-border hover:bg-surface-raised">
      <td className="px-inline-md py-stack-sm">{user.email}</td>
      <td className="px-inline-md py-stack-sm">{t(`user.role.${user.role}`)}</td>
      <td className="px-inline-md py-stack-sm">
        <span
          className={
            user.status === 'active'
              ? 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-success-muted text-success'
              : 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-muted-bg text-muted'
          }
        >
          {t(`user.status.${user.status}`)}
        </span>
      </td>
      <td className="px-inline-md py-stack-sm text-muted">
        {user.created_at !== undefined ? user.created_at.slice(0, 10) : '—'}
      </td>
      <td className="px-inline-md py-stack-sm">
        {user.id !== currentUserId && (
          <button
            type="button"
            onClick={() => {
              onDelete(user.id, user.email);
            }}
            className="text-danger text-label-sm hover:underline"
          >
            {t('common.buttons.delete')}
          </button>
        )}
      </td>
    </tr>
  );
}

export function UsersPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [offset, setOffset] = useState(0);
  const [showCreate, setShowCreate] = useState(false);
  const session = authStore.getSession();
  const currentUserId = session?.userId ?? null;

  const { data, isLoading, isError } = useUsers(PAGE_SIZE, offset);
  const deleteMutation = useDeleteUser();

  const users = data?.items ?? [];
  const total = data?.total ?? 0;

  function handleDelete(id: number, email: string) {
    if (!window.confirm(t('user.messages.delete_confirm', { email }))) return;
    deleteMutation.mutate(id);
  }

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  return (
    <AppShell onLogout={handleLogout}>
      <Stack gap="lg">
        <div className="flex items-center justify-between">
          <Text as="h1" className="text-heading-md">
            {t('user.list.title')}
          </Text>
          <Button
            variant="primary"
            onClick={() => {
              setShowCreate(true);
            }}
          >
            {t('user.list.invite_button')}
          </Button>
        </div>

        {isError && <Text tone="danger">{t('common.status.error')}</Text>}

        {isLoading ? (
          <Text tone="muted">{t('common.status.loading')}</Text>
        ) : (
          <div className="rounded-lg border border-border bg-surface">
            {users.length === 0 ? (
              <div className="flex items-center justify-center py-stack-xl">
                <Text tone="muted">{t('user.list.empty')}</Text>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-body-sm">
                  <thead>
                    <tr className="border-b border-border bg-surface-raised">
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('user.list.table.email')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('user.list.table.role')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('user.list.table.status')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('user.list.table.created_at')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('user.list.table.actions')}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {users.map((user) => (
                      <UserRow
                        key={user.id}
                        user={user}
                        currentUserId={currentUserId}
                        onDelete={handleDelete}
                      />
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <Pagination
              offset={offset}
              limit={PAGE_SIZE}
              total={total}
              canPrev={offset > 0}
              canNext={offset + PAGE_SIZE < total}
              onPrev={() => {
                setOffset((o) => Math.max(0, o - PAGE_SIZE));
              }}
              onNext={() => {
                setOffset((o) => o + PAGE_SIZE);
              }}
            />
          </div>
        )}
      </Stack>

      {showCreate && (
        <UserFormModal
          onClose={() => {
            setShowCreate(false);
          }}
        />
      )}
    </AppShell>
  );
}
