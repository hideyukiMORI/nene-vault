import {
  Badge,
  Button,
  Card,
  type DataColumn,
  DataTable,
  EmptyState,
  FormField,
  InlineAlert,
  Input,
  Modal,
  Pagination,
  Select,
  Stack,
} from '@hideyukimori/nene2-ui';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { VALIDATION, fieldErrorText } from '@/shared/i18n/validation-keys';
import { authStore } from '@/shared/api/auth-session';
import { useUsers, useCreateUser, useDeleteUser } from '@/entities/user';
import type { User } from '@/entities/user';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { PAGINATION_CHROME } from '@/shared/ui/primitives/paginationChrome';
import { AppChrome } from '@/features/app-chrome';
import { BADGE_CHROME } from '@/shared/ui/primitives/badgeBase';
import {
  TABLE_CARDS,
  TABLE_CHROME,
  TABLE_CARD_TITLE_COL1,
} from '@/shared/ui/primitives/tableChrome';

const PAGE_SIZE = 20;

const ROLES = ['admin', 'member', 'viewer'] as const;

/** Kept next to the schema so the rule and the message that reports it cannot drift apart. */
const PASSWORD_MIN_LENGTH = 8;

const createUserSchema = z.object({
  email: z.string().min(1, VALIDATION.required).email(VALIDATION.invalidEmail),
  password: z.string().min(PASSWORD_MIN_LENGTH, VALIDATION.passwordTooShort),
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
    <Modal
      open
      header
      size="sm"
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
        className="p-x-lg"
      >
        <Stack gap="sm">
          <FormField
            id="user-email"
            label={t('user.form.email_label')}
            error={fieldErrorText(t, errors.email)}
          >
            <Input
              type="email"
              placeholder={t('user.form.email_placeholder')}
              {...register('email')}
            />
          </FormField>
          <FormField
            id="user-password"
            label={t('user.form.password_label')}
            error={fieldErrorText(t, errors.password, { min: PASSWORD_MIN_LENGTH })}
          >
            <Input
              type="password"
              placeholder={t('user.form.password_placeholder')}
              {...register('password')}
            />
          </FormField>
          <FormField id="user-role" label={t('user.form.role_label')}>
            <Select {...register('role')}>
              {ROLES.map((r) => (
                <option key={r} value={r}>
                  {t(`user.role.${r}`)}
                </option>
              ))}
            </Select>
          </FormField>
          {submitError !== null && <p className="text-2xs text-danger">{t(submitError)}</p>}
          <Stack
            direction="horizontal"
            align="center"
            justify="end"
            gap="2xs"
            className="max-md:flex-col-reverse max-md:items-stretch max-md:gap-2.5"
          >
            <Button
              type="button"
              variant="outline"
              tone="neutral"
              onClick={onClose}
              disabled={mutation.isPending}
            >
              {t('common.buttons.cancel')}
            </Button>
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? t('common.status.saving') : t('common.buttons.invite')}
            </Button>
          </Stack>
        </Stack>
      </form>
    </Modal>
  );
}

function userColumns(
  t: ReturnType<typeof useTranslation>['t'],
  currentUserId: number | null,
  onDelete: (id: number, email: string) => void,
): DataColumn<User>[] {
  return [
    {
      key: 'email',
      header: t('user.list.table.email'),
      cell: (user) => <span className="font-semibold text-x-ink-deep">{user.email}</span>,
    },
    { key: 'role', header: t('user.list.table.role'), cell: (user) => t(`user.role.${user.role}`) },
    {
      key: 'status',
      header: t('user.list.table.status'),
      cell: (user) => (
        <Badge tone={user.status === 'active' ? 'success' : 'neutral'} className={BADGE_CHROME}>
          {t(`user.status.${user.status}`)}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      header: t('user.list.table.created_at'),
      cell: (user) => (
        <span className="text-text-muted font-mono zero-slash">{user.created_at.slice(0, 10)}</span>
      ),
    },
    {
      key: 'actions',
      header: t('user.list.table.actions'),
      cell: (user) =>
        user.id !== currentUserId ? (
          <button
            type="button"
            /* `.link.is-danger` was one rule overriding the colour; the override is just the later
               utility here (#428). */
            className="text-accent bg-none border-0 cursor-pointer text-sm leading-inherit no-underline hover:text-x-navy-deep hover:underline hover:underline-offset-2 text-danger"
            onClick={() => {
              onDelete(user.id, user.email);
            }}
          >
            {t('common.buttons.delete')}
          </button>
        ) : null,
    },
  ];
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
        <Stack gap="2xs">
          <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
            {t('navigation.group_admin')}
          </span>
          <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
            {t('user.list.title')}
          </h1>
        </Stack>
        <Button
          onClick={() => {
            setShowCreate(true);
          }}
        >
          {t('user.list.invite_button')}
        </Button>
      </div>

      {isError && <InlineAlert tone="danger">{t('common.status.error')}</InlineAlert>}

      {isLoading ? (
        <EmptyState message={t('common.status.loading')} />
      ) : (
        <Card pad="none">
          {users.length === 0 ? (
            <EmptyState message={t('user.list.empty')} />
          ) : (
            <div className="overflow-x-auto">
              <DataTable
                className={`${TABLE_CHROME} ${TABLE_CARDS} ${TABLE_CARD_TITLE_COL1}`}
                columns={userColumns(t, currentUserId, handleDelete)}
                rows={users}
                rowKey={(user) => String(user.id)}
                caption={t('user.list.title')}
                collapse="sm"
              />
            </div>
          )}
          {total > 0 && (
            <Pagination
              label={t('common.pagination.label')}
              className={PAGINATION_CHROME}
              size="sm"
              statusPlacement="start"
              canPrev={offset > 0}
              canNext={offset + PAGE_SIZE < total}
              onPrev={() => {
                setOffset((o) => Math.max(0, o - PAGE_SIZE));
              }}
              onNext={() => {
                setOffset((o) => o + PAGE_SIZE);
              }}
              status={t('common.pagination.showing', {
                from: String(offset + 1),
                to: String(Math.min(offset + PAGE_SIZE, total)),
                total: String(total),
              })}
              previousLabel={t('common.buttons.previous')}
              nextLabel={t('common.buttons.next')}
            />
          )}
        </Card>
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
