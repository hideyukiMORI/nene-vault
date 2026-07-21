import { dynamicMessageKey } from '@/shared/i18n/catalogs';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuditEvents, diffAuditEvent, formatAuditValue } from '@/entities/audit';
import type { ListAuditEventsParams, AuditEvent, AuditDiffField } from '@/entities/audit';
import { authStore } from '@/shared/api/auth-session';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppChrome } from '@/features/app-chrome';
import { Button } from '@/shared/ui/primitives/Button';
import { Callout } from '@/shared/ui/components/Callout';
import { EmptyState } from '@/shared/ui/components/EmptyState';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Pagination } from '@/shared/ui/components/Pagination';

const PAGE_SIZE = 20;

const ChevronIcon = (
  <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="m9 6 6 6-6 6" />
  </svg>
);
const ArrowIcon = (
  <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M5 12h14M13 6l6 6-6 6" />
  </svg>
);

/** Compact "key: before → after (+N more)" summary shown in the table row. */
function ChangeSummary({ event }: { event: AuditEvent }) {
  const { t } = useTranslation();
  const fields = diffAuditEvent(event.before_json, event.after_json);

  if (event.before_json === null) {
    return (
      <div className="chg-sum">
        <span className="chg-kv">
          <span className="k">{t('audit_event.summary.created')}</span>
        </span>
        <span className="chg-more">
          {t('audit_event.summary.fields', { count: String(fields.length) })}
        </span>
      </div>
    );
  }

  const first = fields[0];
  if (first === undefined) {
    return <span className="chg-more">—</span>;
  }
  return (
    <div className="chg-sum">
      <span className="chg-kv">
        <span className="k">{first.key}</span>
        <span className="b">{formatAuditValue(first.before)}</span>
        <span className="ar">→</span>
        <span className="a">{formatAuditValue(first.after)}</span>
      </span>
      {fields.length > 1 && (
        <span className="chg-more">
          {t('audit_event.summary.more', { count: String(fields.length - 1) })}
        </span>
      )}
    </div>
  );
}

interface DrawerProps {
  event: AuditEvent | null;
  open: boolean;
  onClose: () => void;
}

function DiffView({ fields, isCreate }: { fields: AuditDiffField[]; isCreate: boolean }) {
  const { t } = useTranslation();
  if (fields.length === 0) {
    return <div className="diff-empty">{t('audit_event.detail.no_params')}</div>;
  }
  return (
    <div>
      {fields.map((f) => {
        const tag =
          f.kind === 'add' ? (
            <span className="tag-chg add">{t('audit_event.detail.tag_added')}</span>
          ) : (
            <span className="tag-chg mod">{t('audit_event.detail.tag_changed')}</span>
          );
        return (
          <div key={f.key} className={isCreate ? 'diff-field diff-single' : 'diff-field'}>
            <div className="diff-key">
              {f.key} {tag}
            </div>
            <div className="diff-pair">
              {!isCreate && (
                <div className="diff-val diff-before">{formatAuditValue(f.before)}</div>
              )}
              {!isCreate && <div className="diff-arrow">{ArrowIcon}</div>}
              <div className="diff-val diff-after">{formatAuditValue(f.after)}</div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function AuditDetailDrawer({ event, open, onClose }: DrawerProps) {
  const { t, locale } = useTranslation();
  const [view, setView] = useState<'diff' | 'json'>('diff');

  // Reset to the diff view whenever a new record is opened — the
  // adjust-state-during-render pattern (react-hooks v7 forbids the
  // setState-in-effect shape this replaced).
  const [openedEventId, setOpenedEventId] = useState<string | number | null>(null);
  const currentKey = open ? (event?.id ?? null) : null;
  if (currentKey !== openedEventId) {
    setOpenedEventId(currentKey);
    if (currentKey !== null) setView('diff');
  }

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);

  const isCreate = event !== null && event.before_json === null;
  const fields = event !== null ? diffAuditEvent(event.before_json, event.after_json) : [];

  return (
    <>
      <button
        type="button"
        className={open ? 'drawer-overlay is-open' : 'drawer-overlay'}
        aria-label={t('common.buttons.close')}
        tabIndex={open ? 0 : -1}
        onClick={onClose}
      />
      <aside
        className={open ? 'drawer is-open' : 'drawer'}
        role="dialog"
        aria-modal="true"
        aria-hidden={!open}
      >
        {event !== null && (
          <>
            <div className="drawer-head">
              <div>
                <div className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold mb-1.25">
                  {t('audit_event.detail.record')} #{event.id}
                </div>
                <h2>
                  <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
                  <span>{t(dynamicMessageKey(`audit_event.action.${event.action}`))}</span>
                </h2>
              </div>
              <button
                type="button"
                className="drawer-close"
                aria-label={t('common.buttons.close')}
                onClick={onClose}
              >
                ×
              </button>
            </div>

            <div className="drawer-body">
              <dl className="drawer-meta">
                <div>
                  <dt>{t('audit_event.list.table.actor')}</dt>
                  <dd className="font-mono zero-slash">
                    {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
                  </dd>
                </div>
                <div>
                  <dt>{t('audit_event.list.table.timestamp')}</dt>
                  <dd className="font-mono zero-slash">
                    {formatDateTime(event.created_at, locale)}
                  </dd>
                </div>
                <div className="col2">
                  <dt>{t('audit_event.detail.entity')}</dt>
                  <dd className="font-mono zero-slash label-xs break-all">
                    {event.entity_type}/{event.entity_id}
                  </dd>
                </div>
              </dl>

              <div className="params-head">
                <span className="text-body font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25">
                  <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
                  {t('audit_event.detail.params')}{' '}
                  <span className="count">
                    · {t('audit_event.summary.fields', { count: String(fields.length) })}
                  </span>
                </span>
                <div className="seg">
                  <button
                    type="button"
                    className={view === 'diff' ? 'is-on' : ''}
                    onClick={() => {
                      setView('diff');
                    }}
                  >
                    {t('audit_event.detail.view_diff')}
                  </button>
                  <button
                    type="button"
                    className={view === 'json' ? 'is-on' : ''}
                    onClick={() => {
                      setView('json');
                    }}
                  >
                    {t('audit_event.detail.view_json')}
                  </button>
                </div>
              </div>

              {view === 'diff' ? (
                <DiffView fields={fields} isCreate={isCreate} />
              ) : (
                <div>
                  {!isCreate && (
                    <div className="json-block">
                      <div className="json-label">{t('audit_event.list.table.before')}</div>
                      <pre className="json-pre">{JSON.stringify(event.before_json, null, 2)}</pre>
                    </div>
                  )}
                  <div className="json-block">
                    <div className="json-label">
                      {isCreate
                        ? t('audit_event.detail.created_values')
                        : t('audit_event.list.table.after')}
                    </div>
                    <pre className="json-pre">{JSON.stringify(event.after_json, null, 2)}</pre>
                  </div>
                </div>
              )}
            </div>
          </>
        )}
      </aside>
    </>
  );
}

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
  const [selected, setSelected] = useState<AuditEvent | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const params: ListAuditEventsParams = { ...committed, offset };
  const { data, isLoading, isError } = useAuditEvents(params);

  const events = data?.items ?? [];
  const total = data?.total ?? 0;

  function openDrawer(event: AuditEvent) {
    setSelected(event);
    setDrawerOpen(true);
  }

  function handleSearch() {
    setOffset(0);
    setCommitted({
      limit: PAGE_SIZE,
      offset: 0,
      ...(filterEntityType !== '' && { entity_type: filterEntityType }),
      ...(filterEntityId !== '' && { entity_id: filterEntityId }),
      ...(filterAction !== '' && { action: filterAction }),
    });
  }

  function handleLogout() {
    authStore.clearSession();
    void navigate('/login', { replace: true });
  }

  function handleReset() {
    setFilterEntityType('');
    setFilterEntityId('');
    setFilterAction('');
    setOffset(0);
    setCommitted({ limit: PAGE_SIZE, offset: 0 });
  }

  return (
    <AppChrome onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="flex flex-col gap-1.5">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('navigation.group_admin')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
          {t('audit_event.list.title')}
        </h1>
        <p className="text-text-muted text-sm max-w-lede">{t('audit_event.list.lede')}</p>
      </div>

      <div className="card p-4.5 space-y-4">
        <div className="grid grid-cols-3 gap-4">
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
        <div className="flex items-center gap-2 justify-end">
          <Button variant="secondary" onClick={handleReset}>
            {t('document.search.reset_button')}
          </Button>
          <Button variant="primary" onClick={handleSearch} disabled={isLoading}>
            {t('document.search.search_button')}
          </Button>
        </div>
      </div>

      {isError && <Callout tone="danger">{t('common.status.error')}</Callout>}

      {isLoading ? (
        <EmptyState>{t('common.status.loading')}</EmptyState>
      ) : (
        <div className="card flush">
          {events.length === 0 ? (
            <EmptyState>{t('audit_event.list.empty')}</EmptyState>
          ) : (
            <div className="tbl-wrap">
              <table className="tbl audit-table">
                <thead>
                  <tr>
                    <th>{t('audit_event.list.table.action')}</th>
                    <th>{t('audit_event.list.table.entity')}</th>
                    <th>{t('audit_event.list.table.actor')}</th>
                    <th>{t('audit_event.list.table.timestamp')}</th>
                    <th>{t('audit_event.list.table.changes')}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {events.map((event) => (
                    <tr
                      key={event.id}
                      className="audit-row"
                      role="button"
                      tabIndex={0}
                      onClick={() => {
                        openDrawer(event);
                      }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.preventDefault();
                          openDrawer(event);
                        }
                      }}
                    >
                      <td className="pri">
                        {t(dynamicMessageKey(`audit_event.action.${event.action}`))}
                      </td>
                      <td className="text-text-muted font-mono zero-slash label-xs">
                        {event.entity_type}/{event.entity_id}
                      </td>
                      <td className="text-text-muted">
                        {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
                      </td>
                      <td className="text-text-muted font-mono zero-slash">
                        {formatDateTime(event.created_at, locale)}
                      </td>
                      <td>
                        <ChangeSummary event={event} />
                      </td>
                      <td className="chev-cell">
                        <span className="row-chev">{ChevronIcon}</span>
                      </td>
                    </tr>
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

      <AuditDetailDrawer
        event={selected}
        open={drawerOpen}
        onClose={() => {
          setDrawerOpen(false);
        }}
      />
    </AppChrome>
  );
}
