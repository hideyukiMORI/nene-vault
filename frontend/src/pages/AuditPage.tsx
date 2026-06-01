import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuditEvents } from '@/entities/audit';
import type { ListAuditEventsParams } from '@/entities/audit';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Field, Input, Pagination } from '@/shared/ui';

const PAGE_SIZE = 20;

export function AuditPage() {
  const { t, locale } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();

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
    <AppShell onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="titlebar">
        <span className="eyebrow">{t('navigation.group_admin')}</span>
        <h1 className="page-title">{t('audit_event.list.title')}</h1>
      </div>

      <div className="card p-md stack-md">
        <div className="grid-3">
          <Field label={t('audit_event.list.filter.entity_type_label')}>
            <Input
              type="text"
              value={filterEntityType}
              onChange={(e) => {
                setFilterEntityType(e.target.value);
              }}
            />
          </Field>
          <Field label={t('audit_event.list.filter.entity_id_label')}>
            <Input
              type="text"
              value={filterEntityId}
              onChange={(e) => {
                setFilterEntityId(e.target.value);
              }}
            />
          </Field>
          <Field label={t('audit_event.list.filter.action_label')}>
            <Input
              type="text"
              value={filterAction}
              onChange={(e) => {
                setFilterAction(e.target.value);
              }}
            />
          </Field>
        </div>
        <div className="row gap-sm end">
          <Button variant="secondary" onClick={handleReset}>
            {t('document.search.reset_button')}
          </Button>
          <Button variant="primary" onClick={handleSearch} disabled={isLoading}>
            {t('document.search.search_button')}
          </Button>
        </div>
      </div>

      {isError && <div className="callout callout-danger">{t('common.status.error')}</div>}

      {isLoading ? (
        <div className="empty-state">{t('common.status.loading')}</div>
      ) : (
        <div className="card flush">
          {events.length === 0 ? (
            <div className="empty-state">{t('audit_event.list.empty')}</div>
          ) : (
            <div className="tbl-wrap">
              <table className="tbl tbl-cards">
                <thead>
                  <tr>
                    <th>{t('audit_event.list.table.action')}</th>
                    <th>{t('audit_event.list.table.entity')}</th>
                    <th>{t('audit_event.list.table.actor')}</th>
                    <th>{t('audit_event.list.table.timestamp')}</th>
                    <th>{t('audit_event.list.table.before')}</th>
                    <th>{t('audit_event.list.table.after')}</th>
                  </tr>
                </thead>
                <tbody>
                  {events.map((event) => (
                    <tr key={event.id}>
                      <td className="cell-title">
                        <span className="pri">{t(`audit_event.action.${event.action}`)}</span>
                      </td>
                      <td className="muted mono" data-label={t('audit_event.list.table.entity')}>
                        {event.entity_type}/{event.entity_id}
                      </td>
                      <td className="muted mono" data-label={t('audit_event.list.table.actor')}>
                        {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
                      </td>
                      <td className="muted mono" data-label={t('audit_event.list.table.timestamp')}>
                        {formatDateTime(event.created_at, locale)}
                      </td>
                      <td data-label={t('audit_event.list.table.before')}>
                        {event.before_json !== null ? (
                          <pre className="tbl-diff">
                            {JSON.stringify(event.before_json, null, 2)}
                          </pre>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td data-label={t('audit_event.list.table.after')}>
                        {event.after_json !== null ? (
                          <pre className="tbl-diff">
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
    </AppShell>
  );
}
