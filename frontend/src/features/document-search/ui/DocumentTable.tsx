import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate } from '@/shared/lib/format';
import type { VaultDocument } from '@/entities/document';

interface DocumentTableProps {
  documents: VaultDocument[];
  onSelectDocument: (id: string) => void;
}

export function DocumentTable({ documents, onSelectDocument }: DocumentTableProps) {
  const { t, locale } = useTranslation();

  if (documents.length === 0) {
    return <div className="empty-state">{t('document.list.empty')}</div>;
  }

  return (
    <div className="tbl-wrap">
      <table className="tbl">
        <thead>
          <tr>
            <th>{t('document.list.table.transaction_date')}</th>
            <th>{t('document.list.table.counterparty_name')}</th>
            <th className="right">{t('document.list.table.amount')}</th>
            <th>{t('document.list.table.category')}</th>
            <th>{t('document.list.table.status')}</th>
            <th>{t('document.list.table.uploaded_at')}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {documents.map((doc) => (
            <tr key={doc.id}>
              <td className="mono">
                {formatDate(doc.transaction_date)}
                {doc.date_uncertain && <span className="faint"> *</span>}
              </td>
              <td>
                <span className="pri">{doc.counterparty_name}</span>
              </td>
              <td className="right tabular mono">{formatJpy(doc.amount_cents, locale)}</td>
              <td>{t(`document.category.${doc.category}`)}</td>
              <td>
                <span
                  className={doc.status === 'voided' ? 'badge badge-danger' : 'badge badge-success'}
                >
                  {t(`document.status.${doc.status}`)}
                </span>
              </td>
              <td className="muted mono">{doc.uploaded_at.slice(0, 10)}</td>
              <td className="right">
                <button
                  type="button"
                  className="link"
                  onClick={() => {
                    onSelectDocument(doc.id);
                  }}
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
