import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate } from '@/shared/lib/format';
import { Text } from '@/shared/ui';
import type { VaultDocument } from '@/entities/document';

interface DocumentTableProps {
  documents: VaultDocument[];
  onSelectDocument: (id: string) => void;
}

export function DocumentTable({ documents, onSelectDocument }: DocumentTableProps) {
  const { t, locale } = useTranslation();

  if (documents.length === 0) {
    return (
      <div className="flex items-center justify-center py-stack-xl text-muted">
        <Text tone="muted">{t('document.list.empty')}</Text>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-body-sm">
        <thead>
          <tr className="border-b border-border bg-surface-raised">
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.transaction_date')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.counterparty_name')}
            </th>
            <th className="px-inline-md py-stack-sm text-right text-label-sm font-medium text-muted">
              {t('document.list.table.amount')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.category')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.status')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.uploaded_at')}
            </th>
            <th className="px-inline-md py-stack-sm text-left text-label-sm font-medium text-muted">
              {t('document.list.table.actions')}
            </th>
          </tr>
        </thead>
        <tbody>
          {documents.map((doc) => (
            <tr
              key={doc.id}
              className="border-b border-border hover:bg-surface-raised transition-colors"
            >
              <td className="px-inline-md py-stack-sm">
                {doc.date_uncertain ? (
                  <span className="text-muted">{formatDate(doc.transaction_date)} *</span>
                ) : (
                  formatDate(doc.transaction_date)
                )}
              </td>
              <td className="px-inline-md py-stack-sm font-medium">{doc.counterparty_name}</td>
              <td className="px-inline-md py-stack-sm text-right tabular-nums">
                {formatJpy(doc.amount_cents, locale)}
              </td>
              <td className="px-inline-md py-stack-sm">{t(`document.category.${doc.category}`)}</td>
              <td className="px-inline-md py-stack-sm">
                <span
                  className={
                    doc.status === 'voided'
                      ? 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-danger-muted text-danger'
                      : 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-success-muted text-success'
                  }
                >
                  {t(`document.status.${doc.status}`)}
                </span>
              </td>
              <td className="px-inline-md py-stack-sm text-muted">
                {doc.uploaded_at.slice(0, 10)}
              </td>
              <td className="px-inline-md py-stack-sm">
                <button
                  type="button"
                  onClick={() => {
                    onSelectDocument(doc.id);
                  }}
                  className="text-brand underline-offset-2 hover:underline text-label-sm"
                >
                  {t('common.buttons.view_detail')}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
