import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuditEvents } from '@/entities/audit';
import type { ListAuditEventsParams } from '@/entities/audit';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Field, Input, Pagination, Stack, Text } from '@/shared/ui';

const PAGE_SIZE = 20;

export function AuditPage() {
  const { t, locale } = useTranslation();
  const navigate = useNavigate();

  const [filterEntityType, setFilterEntityType] = useState('');
  const [filterEntityId, setFilterEntityId] = useState('');
  const [filterAction, setFilterAction] = useState('');
  const [committed, setCommitted] = useState<ListAuditEventsParams>({
    limit: PAGE_SIZE,
    offset: 0,
  });
  const [offset, setOffset] = useState(0);

  const params: ListAuditEventsParams = { ...committed, offset };
  const { data, isLoading, isError } = useAuditEvents(params);

  const events = data?.items ?? [];
  const total = data?.total ?? 0;

  function handleSearch() {
    setOffset(0);
    setCommitted({
      limit: PAGE_SIZE,
      offset: 0,
      entity_type: filterEntityType !== '' ? filterEntityType : undefined,
      entity_id: filterEntityId !== '' ? filterEntityId : undefined,
      action: filterAction !== '' ? filterAction : undefined,
    });
  }

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  function handleReset() {
    setFilterEntityType('');
    setFilterEntityId('');
    setFilterAction('');
    setOffset(0);
    setCommitted({ limit: PAGE_SIZE, offset: 0 });
  }

  return (
    <AppShell onLogout={handleLogout}>
      <Stack gap="lg">
        <Text as="h1" className="text-heading-md">
          {t('audit_event.list.title')}
        </Text>

        <div className="rounded-lg border border-border bg-surface-raised p-stack-md">
          <div className="grid grid-cols-3 gap-inline-md">
            <Field label={t('audit_event.list.filter.entity_type_label')} labelTone="muted">
              <Input
                type="text"
                value={filterEntityType}
                onChange={(e) => {
                  setFilterEntityType(e.target.value);
                }}
              />
            </Field>
            <Field label={t('audit_event.list.filter.entity_id_label')} labelTone="muted">
              <Input
                type="text"
                value={filterEntityId}
                onChange={(e) => {
                  setFilterEntityId(e.target.value);
                }}
              />
            </Field>
            <Field label={t('audit_event.list.filter.action_label')} labelTone="muted">
              <Input
                type="text"
                value={filterAction}
                onChange={(e) => {
                  setFilterAction(e.target.value);
                }}
              />
            </Field>
          </div>
          <div className="mt-stack-md flex gap-inline-md">
            <Button variant="primary" onClick={handleSearch} disabled={isLoading}>
              {t('document.search.search_button')}
            </Button>
            <Button variant="secondary" onClick={handleReset}>
              {t('document.search.reset_button')}
            </Button>
          </div>
        </div>

        {isError && <Text tone="danger">{t('common.status.error')}</Text>}

        {isLoading ? (
          <Text tone="muted">{t('common.status.loading')}</Text>
        ) : (
          <div className="rounded-lg border border-border bg-surface">
            {events.length === 0 ? (
              <div className="flex items-center justify-center py-stack-xl">
                <Text tone="muted">{t('audit_event.list.empty')}</Text>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-body-sm">
                  <thead>
                    <tr className="border-b border-border bg-surface-raised">
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.action')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.entity')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.actor')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.timestamp')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.before')}
                      </th>
                      <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
                        {t('audit_event.list.table.after')}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {events.map((event) => (
                      <tr key={event.id} className="border-b border-border hover:bg-surface-raised">
                        <td className="px-inline-md py-stack-sm font-medium">
                          {t(`audit_event.action.${event.action}`)}
                        </td>
                        <td className="px-inline-md py-stack-sm text-muted">
                          {event.entity_type}/{event.entity_id}
                        </td>
                        <td className="px-inline-md py-stack-sm text-muted">
                          {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
                        </td>
                        <td className="px-inline-md py-stack-sm text-muted">
                          {formatDateTime(event.created_at, locale)}
                        </td>
                        <td className="px-inline-md py-stack-sm">
                          {event.before_json !== null ? (
                            <pre className="text-label-xs text-muted max-w-48 overflow-hidden whitespace-pre-wrap">
                              {JSON.stringify(event.before_json, null, 2)}
                            </pre>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="px-inline-md py-stack-sm">
                          {event.after_json !== null ? (
                            <pre className="text-label-xs text-muted max-w-48 overflow-hidden whitespace-pre-wrap">
                              {JSON.stringify(event.after_json, null, 2)}
                            </pre>
                          ) : (
                            '—'
                          )}
                        </td>
                      </tr>
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
    </AppShell>
  );
}
