import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authStore } from '@/shared/api/auth-session';
import { useUsers, useCreateUser, useDeleteUser } from '@/entities/user';
import type { User } from '@/entities/user';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppChrome } from '@/features/app-chrome';
import { Button } from '@/shared/ui/primitives/Button';
import { Callout } from '@/shared/ui/components/Callout';
import { EmptyState } from '@/shared/ui/components/EmptyState';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Modal } from '@/shared/ui/components/Modal';
import { Pagination } from '@/shared/ui/components/Pagination';
import { Select } from '@/shared/ui/primitives/Select';

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

  const requiredMarker = t('common.required_marker');

  return (
    <Modal
      title={t('user.form.create_title')}
      onClose={onClose}
      closeLabel={t('common.buttons.close')}
    >
      <form
        onSubmit={(e) => {
          void form.handleSubmit((values) => {
            mutation.mutate(values);
          })(e);
        }}
        className="modal-body space-y-4"
      >
        <Field
          label={t('user.form.email_label')}
          error={errors.email !== undefined ? requiredMarker : undefined}
        >
          <Input
            type="email"
            placeholder={t('user.form.email_placeholder')}
            {...register('email')}
          />
        </Field>
        <Field
          label={t('user.form.password_label')}
          error={errors.password !== undefined ? requiredMarker : undefined}
        >
          <Input
            type="password"
            placeholder={t('user.form.password_placeholder')}
            {...register('password')}
          />
        </Field>
        <Field label={t('user.form.role_label')}>
          <Select {...register('role')}>
            {ROLES.map((r) => (
              <option key={r} value={r}>
                {t(`user.role.${r}`)}
              </option>
            ))}
          </Select>
        </Field>
        {submitError !== null && <p className="field-error">{t(submitError)}</p>}
        <div className="flex items-center justify-end gap-2 max-md:flex-col-reverse max-md:items-stretch max-md:gap-2.5">
          <Button type="button" variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.buttons.cancel')}
          </Button>
          <Button type="submit" variant="primary" disabled={mutation.isPending}>
            {mutation.isPending ? t('common.status.saving') : t('common.buttons.invite')}
          </Button>
        </div>
      </form>
    </Modal>
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
    <tr>
      <td className="cell-title">
        <span className="pri">{user.email}</span>
      </td>
      <td data-label={t('user.list.table.role')}>{t(`user.role.${user.role}`)}</td>
      <td data-label={t('user.list.table.status')}>
        <span
          className="badge data-[tone=success]:bg-success-soft data-[tone=success]:text-success data-[tone=muted]:bg-x-sunk-deep data-[tone=muted]:text-text-muted"
          data-tone={user.status === 'active' ? 'success' : 'muted'}
        >
          {t(`user.status.${user.status}`)}
        </span>
      </td>
      <td
        className="text-text-muted font-mono zero-slash"
        data-label={t('user.list.table.created_at')}
      >
        {user.created_at.slice(0, 10)}
      </td>
      <td data-label={t('user.list.table.actions')}>
        {user.id !== currentUserId && (
          <button
            type="button"
            className="link is-danger"
            onClick={() => {
              onDelete(user.id, user.email);
            }}
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
    void navigate('/login', { replace: true });
  }

  return (
    <AppChrome onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="flex items-end justify-between gap-4 max-md:flex-col max-md:items-start max-md:gap-3.5">
        <div className="flex flex-col gap-1.5">
          <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
            {t('navigation.group_admin')}
          </span>
          <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
            {t('user.list.title')}
          </h1>
        </div>
        <Button
          variant="primary"
          onClick={() => {
            setShowCreate(true);
          }}
        >
          {t('user.list.invite_button')}
        </Button>
      </div>

      {isError && <Callout tone="danger">{t('common.status.error')}</Callout>}

      {isLoading ? (
        <EmptyState>{t('common.status.loading')}</EmptyState>
      ) : (
        <div className="card flush">
          {users.length === 0 ? (
            <EmptyState>{t('user.list.empty')}</EmptyState>
          ) : (
            <div className="tbl-wrap">
              <table className="tbl tbl-cards">
                <thead>
                  <tr>
                    <th>{t('user.list.table.email')}</th>
                    <th>{t('user.list.table.role')}</th>
                    <th>{t('user.list.table.status')}</th>
                    <th>{t('user.list.table.created_at')}</th>
                    <th>{t('user.list.table.actions')}</th>
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
            total={total}
            canPrev={offset > 0}
            canNext={offset + PAGE_SIZE < total}
            onPrev={() => {
              setOffset((o) => Math.max(0, o - PAGE_SIZE));
            }}
            onNext={() => {
              setOffset((o) => o + PAGE_SIZE);
            }}
            showingLabel={t('common.pagination.showing', {
              from: String(offset + 1),
              to: String(Math.min(offset + PAGE_SIZE, total)),
              total: String(total),
            })}
            previousLabel={t('common.buttons.previous')}
            nextLabel={t('common.buttons.next')}
          />
        </div>
      )}

      {showCreate && (
        <UserFormModal
          onClose={() => {
            setShowCreate(false);
          }}
        />
      )}
    </AppChrome>
  );
}
