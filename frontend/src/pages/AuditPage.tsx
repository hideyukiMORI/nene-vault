import {
  Button,
  Card,
  EmptyState,
  FormField,
  Grid,
  InlineAlert,
  Input,
  Stack,
} from '@hideyukimori/nene2-ui';
import { dynamicMessageKey } from '@/shared/i18n/catalogs';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuditEvents, diffAuditEvent, formatAuditValue } from '@/entities/audit';
import type { ListAuditEventsParams, AuditEvent, AuditDiffField } from '@/entities/audit';
import { authStore } from '@/shared/api/auth-session';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppChrome } from '@/features/app-chrome';
import { Pagination } from '@/shared/ui/components/Pagination';

const PAGE_SIZE = 20;

const ChevronIcon = (
  <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="m9 6 6 6-6 6" />
  </svg>
);
// The retired `.diff-field` carried one rule — the margin between consecutive
// fields — so it survives as a self-referencing sibling variant rather than a
// class (#371).
const DIFF_FIELD = '[&+&]:mt-3.25';

// The retired `.diff-pair` was written desktop-first (3 columns, overridden to 1
// below 768px). Regenerated mobile-first: one column by default, the 3-column
// template from `md:` up. The two forms agree at every width — `md:` is
// `>= 48rem` = 768px, exactly where the old `@media (max-width: 767px)` block
// stopped applying. `.diff-single` (creation events, no "before" side) is simply
// the absence of `md:diff-cols`: the ancestor selector collapses into the
// `isCreate` branch the call site already has, so no `data-*` dimension is
// invented for a boolean the markup already knows (判例#33 ガード①).
const DIFF_PAIR = 'grid items-stretch grid-cols-1 gap-1.5 md:gap-2';

// `w-3.75 h-3.75 stroke-current` is the old `.diff-arrow svg` rule, and
// `max-md:rotate-90` its `@media (max-width: 767px)` half — expressed on the
// element because this icon has exactly one call site (#371).
const ArrowIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    className="w-3.75 h-3.75 stroke-current max-md:rotate-90"
  >
    <path d="M5 12h14M13 6l6 6-6 6" />
  </svg>
);

/** Compact "key: before → after (+N more)" summary shown in the table row. */
function ChangeSummary({ event }: { event: AuditEvent }) {
  const { t } = useTranslation();
  const fields = diffAuditEvent(event.before_json, event.after_json);

  if (event.before_json === null) {
    return (
      <Stack direction="horizontal" align="center" wrap gap="2xs" className="min-w-0">
        <span className="inline-flex items-center flex-wrap gap-1.75 min-w-0 font-mono text-2xs whitespace-normal">
          <span className="text-text-muted">{t('audit_event.summary.created')}</span>
        </span>
        <span className="text-2xs text-text-faint whitespace-nowrap">
          {t('audit_event.summary.fields', { count: String(fields.length) })}
        </span>
      </Stack>
    );
  }

  const first = fields[0];
  if (first === undefined) {
    return <span className="text-2xs text-text-faint whitespace-nowrap">—</span>;
  }
  return (
    <Stack direction="horizontal" align="center" wrap gap="2xs" className="min-w-0">
      {/* Regenerated from `.chg-kv` and its four descendant rules (#422). The children carry
          their own utilities rather than a `[&_.k]:*` variant — removing the descendant
          selector is the point of the drain, so re-expressing it in another spelling would
          not be one.
          ⚠️ `rounded-sm` is 4px against the old rule's 3px: 3px is not a step of this scale,
          and adding one is upstream's call (#395). Measured, and the only difference left. */}
      <span className="inline-flex items-center flex-wrap gap-1.75 min-w-0 font-mono text-2xs whitespace-normal">
        <span className="text-text-muted">{first.key}</span>
        <span className="text-text-faint bg-surface-sunken border-border border rounded-sm py-px px-1.5 wrap-anywhere">
          {formatAuditValue(first.before)}
        </span>
        <span className="text-text-faint">→</span>
        <span className="text-x-brass-deep bg-x-brass-soft border-x-brass-line border rounded-sm py-px px-1.5 wrap-anywhere">
          {formatAuditValue(first.after)}
        </span>
      </span>
      {fields.length > 1 && (
        <span className="text-2xs text-text-faint whitespace-nowrap">
          {t('audit_event.summary.more', { count: String(fields.length - 1) })}
        </span>
      )}
    </Stack>
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
    return (
      <div className="text-sm text-text-muted bg-surface-overlay border border-dashed border-x-line-mid rounded-md p-4 text-center">
        {t('audit_event.detail.no_params')}
      </div>
    );
  }
  return (
    <div>
      {fields.map((f) => {
        const tag =
          f.kind === 'add' ? (
            <span className="font-sans text-3xs tracking-label font-bold uppercase px-1.5 py-px rounded-full bg-success-soft text-success">
              {t('audit_event.detail.tag_added')}
            </span>
          ) : (
            <span className="font-sans text-3xs tracking-label font-bold uppercase px-1.5 py-px rounded-full bg-x-brass-soft text-x-brass-deep">
              {t('audit_event.detail.tag_changed')}
            </span>
          );
        return (
          <div key={f.key} className={DIFF_FIELD}>
            <div className="font-mono text-xs text-x-ink-deep font-medium mb-1.75 flex items-center gap-2">
              {f.key} {tag}
            </div>
            <div className={isCreate ? DIFF_PAIR : `${DIFF_PAIR} md:diff-cols`}>
              {/* 🔴 The arrow used to be the only thing carrying "this became that", and it
                  carried it visually only: no text sat between the two values, so assistive
                  technology read them as two unrelated strings (#387). Naming both sides is
                  sturdier than labelling the arrow — it still reads correctly for someone who
                  lands on one value directly, and it survives the arrow being restyled. */}
              {!isCreate && (
                <div className="font-mono text-2xs leading-diff rounded-sm py-2 px-2.5 break-all whitespace-pre-wrap bg-surface-sunken border border-border text-text-muted">
                  <span className="sr-only">{t('audit_event.list.table.before')}: </span>
                  {formatAuditValue(f.before)}
                </div>
              )}
              {!isCreate && (
                <div
                  aria-hidden="true"
                  className="flex items-center justify-center text-text-faint max-md:justify-start max-md:py-px max-md:pl-0.75"
                >
                  {ArrowIcon}
                </div>
              )}
              <div className="font-mono text-2xs leading-diff rounded-sm py-2 px-2.5 break-all whitespace-pre-wrap bg-x-brass-soft border border-x-brass-line text-x-brass-deep">
                <span className="sr-only">{t('audit_event.list.table.after')}: </span>
                {formatAuditValue(f.after)}
              </div>
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
        className={
          open
            ? 'fixed inset-0 bg-x-scrim/42 z-scrim transition-fade duration-180 opacity-100 visible'
            : 'fixed inset-0 bg-x-scrim/42 z-scrim transition-fade duration-180 opacity-0 invisible'
        }
        aria-label={t('common.buttons.close')}
        tabIndex={open ? 0 : -1}
        onClick={onClose}
      />
      <aside
        className={
          open
            ? 'fixed top-0 right-0 h-screen w-drawer bg-surface-raised border-l border-x-line-mid shadow-lg z-drawer flex flex-col transition-transform duration-240 ease-drawer translate-x-0 max-md:top-auto max-md:bottom-0 max-md:left-0 max-md:right-0 max-md:w-full max-md:h-auto max-md:max-h-dialog max-md:border-l-0 max-md:border-t max-md:rounded-t-sheet'
            : 'fixed top-0 right-0 h-screen w-drawer bg-surface-raised border-l border-x-line-mid shadow-lg z-drawer flex flex-col transition-transform duration-240 ease-drawer translate-x-full max-md:top-auto max-md:bottom-0 max-md:left-0 max-md:right-0 max-md:w-full max-md:h-auto max-md:max-h-dialog max-md:border-l-0 max-md:border-t max-md:rounded-t-sheet max-md:translate-x-0 max-md:translate-y-full'
        }
        role="dialog"
        aria-modal="true"
        aria-hidden={!open}
      >
        {event !== null && (
          <>
            <div className="flex items-start gap-3 justify-between px-5.5 pt-4.5 pb-4 border-b border-border max-md:pt-5.5 max-md:relative max-md:before:absolute max-md:before:top-2.25 max-md:before:left-1/2 max-md:before:-translate-x-1/2 max-md:before:w-9.5 max-md:before:h-1 max-md:before:rounded-full max-md:before:bg-x-line-mid">
              <div>
                <div className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold mb-1.25">
                  {t('audit_event.detail.record')} #{event.id}
                </div>
                <h2 className="text-h2 font-semibold flex items-center gap-2.25">
                  <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
                  <span>{t(dynamicMessageKey(`audit_event.action.${event.action}`))}</span>
                </h2>
              </div>
              <button
                type="button"
                className="bg-transparent border-0 text-modal-close cursor-pointer text-text-faint leading-none px-1.75 py-0.5 rounded-sm flex-none hover:text-x-ink-deep hover:bg-surface-sunken max-md:w-10 max-md:h-10 max-md:flex max-md:items-center max-md:justify-center max-md:text-2xl"
                aria-label={t('common.buttons.close')}
                onClick={onClose}
              >
                ×
              </button>
            </div>

            <div className="overflow-auto flex-1 pt-5 px-5.5 pb-7">
              <dl className="grid grid-cols-2 gap-x-5.5 gap-y-3.25 pb-4.5 border-b border-border mb-4.5 max-md:grid-cols-1 max-md:gap-3 [&_dt]:text-2xs [&_dt]:text-text-muted [&_dt]:uppercase [&_dt]:tracking-meta [&_dt]:font-semibold [&_dt]:mb-0.75 [&_dd]:text-sm [&_dd]:leading-inherit [&_dd]:text-x-ink-deep">
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
                <div className="col-span-2 max-md:col-auto">
                  <dt>{t('audit_event.detail.entity')}</dt>
                  <dd className="font-mono zero-slash label-xs break-all">
                    {event.entity_type}/{event.entity_id}
                  </dd>
                </div>
              </dl>

              <div className="flex items-center justify-between gap-3 mb-3.5">
                <span className="text-body font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25">
                  <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
                  {t('audit_event.detail.params')}{' '}
                  <span className="text-2xs text-text-faint">
                    · {t('audit_event.summary.fields', { count: String(fields.length) })}
                  </span>
                </span>
                <div
                  /* Regenerated from `.seg` and its three descendant rules (#428).
                     `.seg button + button` was an adjacent-sibling border; written per button
                     as `[&+button]:border-l` so the selector lives on the element that draws it. */
                  className="inline-flex border border-x-line-mid rounded-sm overflow-hidden bg-surface-raised"
                >
                  <button
                    type="button"
                    className={`text-2xs leading-inherit font-semibold py-1.25 px-2.75 border-0 cursor-pointer [&+button]:border-l [&+button]:border-x-line-mid ${view === 'diff' ? 'bg-accent text-on-accent' : 'bg-transparent text-text-muted'}`}
                    onClick={() => {
                      setView('diff');
                    }}
                  >
                    {t('audit_event.detail.view_diff')}
                  </button>
                  <button
                    type="button"
                    className={`text-2xs leading-inherit font-semibold py-1.25 px-2.75 border-0 cursor-pointer [&+button]:border-l [&+button]:border-x-line-mid ${view === 'json' ? 'bg-accent text-on-accent' : 'bg-transparent text-text-muted'}`}
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
                <div
                  /* `.json-block + .json-block { margin-top: 14px }` was an adjacent-sibling
                     rule; `space-y-3.5` on the parent says the same thing without one (#428). */
                  className="space-y-3.5"
                >
                  {!isCreate && (
                    <div>
                      <div className="text-2xs text-text-muted uppercase tracking-label font-semibold mb-1.5">
                        {t('audit_event.list.table.before')}
                      </div>
                      <pre className="font-mono text-2xs leading-pre bg-surface-sunken border border-border rounded-sm py-3 px-3.25 whitespace-pre overflow-x-auto text-text-primary">
                        {JSON.stringify(event.before_json, null, 2)}
                      </pre>
                    </div>
                  )}
                  <div>
                    <div className="text-2xs text-text-muted uppercase tracking-label font-semibold mb-1.5">
                      {isCreate
                        ? t('audit_event.detail.created_values')
                        : t('audit_event.list.table.after')}
                    </div>
                    <pre className="font-mono text-2xs leading-pre bg-surface-sunken border border-border rounded-sm py-3 px-3.25 whitespace-pre overflow-x-auto text-text-primary">
                      {JSON.stringify(event.after_json, null, 2)}
                    </pre>
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
      <Stack gap="2xs">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('navigation.group_admin')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
          {t('audit_event.list.title')}
        </h1>
        <p className="text-text-muted text-sm leading-inherit max-w-lede">
          {t('audit_event.list.lede')}
        </p>
      </Stack>

      <Card raised pad="md">
        <Stack gap="sm">
          <Grid cols={3} gap="sm">
            <FormField
              id="audit-filter-entity-type"
              label={t('audit_event.list.filter.entity_type_label')}
            >
              <Input
                type="text"
                value={filterEntityType}
                onChange={(e) => {
                  setFilterEntityType(e.target.value);
                }}
              />
            </FormField>
            <FormField
              id="audit-filter-entity-id"
              label={t('audit_event.list.filter.entity_id_label')}
            >
              <Input
                type="text"
                value={filterEntityId}
                onChange={(e) => {
                  setFilterEntityId(e.target.value);
                }}
              />
            </FormField>
            <FormField id="audit-filter-action" label={t('audit_event.list.filter.action_label')}>
              <Input
                type="text"
                value={filterAction}
                onChange={(e) => {
                  setFilterAction(e.target.value);
                }}
              />
            </FormField>
          </Grid>
          <Stack direction="horizontal" align="center" justify="end" gap="2xs">
            <Button variant="secondary" onClick={handleReset}>
              {t('document.search.reset_button')}
            </Button>
            <Button variant="primary" onClick={handleSearch} disabled={isLoading}>
              {t('document.search.search_button')}
            </Button>
          </Stack>
        </Stack>
      </Card>

      {isError && <InlineAlert tone="danger">{t('common.status.error')}</InlineAlert>}

      {isLoading ? (
        <EmptyState message={t('common.status.loading')} />
      ) : (
        <Card pad="none">
          {events.length === 0 ? (
            <EmptyState message={t('audit_event.list.empty')} />
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
                        {/* The row itself is the control (tabIndex + Enter/Space); the
                            chevron only points at what the row already announces. */}
                        <span className="row-chev" aria-hidden="true">
                          {ChevronIcon}
                        </span>
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
        </Card>
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
