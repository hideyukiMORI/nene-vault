import { DataTable, type DataColumn } from '@hideyukimori/nene2-ui';
import { dynamicMessageKey } from '@/shared/i18n/catalogs';
import { useTranslation } from '@/shared/i18n/use-translation';
import { TABLE_CARDS, TABLE_CHROME } from '@/shared/ui/primitives/tableChrome';
import { formatDateTime } from '@/shared/lib/format';
import type { AuditEvent } from '@/entities/audit';

interface DocumentHistoryTableProps {
  events: AuditEvent[];
}

const PRE =
  'font-mono text-2xs text-text-muted bg-surface-sunken border border-border rounded-sm py-1.75 px-2.25 whitespace-pre-wrap max-w-56 leading-normal overflow-hidden';

function Json({ value }: { value: unknown }) {
  return value !== null ? <pre className={PRE}>{JSON.stringify(value, null, 2)}</pre> : <>—</>;
}

/** The per-document change history on the kit's `DataTable` (0.17.0, W1b). */
export function DocumentHistoryTable({ events }: DocumentHistoryTableProps) {
  const { t, locale } = useTranslation();

  if (events.length === 0) {
    return <p className="text-text-muted body-sm">{t('document.history.no_history')}</p>;
  }

  const columns: DataColumn<AuditEvent>[] = [
    {
      key: 'action',
      header: t('document.history.table.action'),
      cell: (event) => (
        <span className="font-semibold text-x-ink-deep">
          {t(dynamicMessageKey(`audit_event.action.${event.action}`))}
        </span>
      ),
    },
    {
      key: 'actor',
      header: t('document.history.table.actor'),
      cell: (event) => (
        <span className="text-text-muted font-mono zero-slash">
          {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
        </span>
      ),
    },
    {
      key: 'timestamp',
      header: t('document.history.table.timestamp'),
      cell: (event) => (
        <span className="text-text-muted font-mono zero-slash">
          {formatDateTime(event.created_at, locale)}
        </span>
      ),
    },
    {
      key: 'before',
      header: t('document.history.table.before'),
      cell: (event) => <Json value={event.before_json} />,
    },
    {
      key: 'after',
      header: t('document.history.table.after'),
      cell: (event) => <Json value={event.after_json} />,
    },
  ];

  return (
    <div className="overflow-x-auto">
      <DataTable
        className={`${TABLE_CHROME} ${TABLE_CARDS}`}
        columns={columns}
        rows={events}
        rowKey={(event) => String(event.id)}
        caption={t('document.history.title')}
        collapse="sm"
      />
    </div>
  );
}
