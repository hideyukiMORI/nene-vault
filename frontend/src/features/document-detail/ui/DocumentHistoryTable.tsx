import { useTranslation } from '@/shared/i18n/use-translation';
import { Text } from '@/shared/ui';
import type { AuditEvent } from '@/entities/audit';

interface DocumentHistoryTableProps {
  events: AuditEvent[];
}

export function DocumentHistoryTable({ events }: DocumentHistoryTableProps) {
  const { t } = useTranslation();

  if (events.length === 0) {
    return <Text tone="muted">{t('document.history.no_history')}</Text>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-body-sm">
        <thead>
          <tr className="border-b border-border bg-surface-raised">
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.history.table.action')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.history.table.actor')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.history.table.timestamp')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.history.table.before')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.history.table.after')}
            </th>
          </tr>
        </thead>
        <tbody>
          {events.map((event) => (
            <tr key={event.id} className="border-b border-border">
              <td className="px-inline-md py-stack-sm font-medium">{event.action}</td>
              <td className="px-inline-md py-stack-sm text-muted">
                {event.actor_user_id !== null ? String(event.actor_user_id) : '—'}
              </td>
              <td className="px-inline-md py-stack-sm text-muted">
                {event.created_at.slice(0, 16).replace('T', ' ')}
              </td>
              <td className="px-inline-md py-stack-sm">
                {event.before_json !== null ? (
                  <pre className="text-label-xs text-muted max-w-48 overflow-hidden text-ellipsis whitespace-pre-wrap">
                    {JSON.stringify(event.before_json, null, 2)}
                  </pre>
                ) : (
                  '—'
                )}
              </td>
              <td className="px-inline-md py-stack-sm">
                {event.after_json !== null ? (
                  <pre className="text-label-xs text-muted max-w-48 overflow-hidden text-ellipsis whitespace-pre-wrap">
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
  );
}
