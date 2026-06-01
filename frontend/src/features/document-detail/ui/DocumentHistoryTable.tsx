import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import type { AuditEvent } from '@/entities/audit';

interface DocumentHistoryTableProps {
  events: AuditEvent[];
}

export function DocumentHistoryTable({ events }: DocumentHistoryTableProps) {
  const { t, locale } = useTranslation();

  if (events.length === 0) {
    return <p className="muted body-sm">{t('document.history.no_history')}</p>;
  }

  return (
    <div className="tbl-wrap">
      <table className="tbl">
        <thead>
          <tr>
            <th>{t('document.history.table.action')}</th>
            <th>{t('document.history.table.actor')}</th>
            <th>{t('document.history.table.timestamp')}</th>
            <th>{t('document.history.table.before')}</th>
            <th>{t('document.history.table.after')}</th>
          </tr>
        </thead>
        <tbody>
          {events.map((event) => (
            <tr key={event.id}>
              <td>
                <span className="pri">{t(`audit_event.action.${event.action}`)}</span>
              </td>
              <td className="muted mono">
                {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
              </td>
              <td className="muted mono">{formatDateTime(event.created_at, locale)}</td>
              <td>
                {event.before_json !== null ? (
                  <pre className="tbl-diff">{JSON.stringify(event.before_json, null, 2)}</pre>
                ) : (
                  '—'
                )}
              </td>
              <td>
                {event.after_json !== null ? (
                  <pre className="tbl-diff">{JSON.stringify(event.after_json, null, 2)}</pre>
                ) : (
                  '—'
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
