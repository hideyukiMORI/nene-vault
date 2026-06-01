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

  const labels = {
    date: t('document.list.table.transaction_date'),
    amount: t('document.list.table.amount'),
    category: t('document.list.table.category'),
    status: t('document.list.table.status'),
    uploaded: t('document.list.table.uploaded_at'),
    actions: t('document.list.table.actions'),
  };

  return (
    <div className="tbl-wrap">
      {/* tbl-cards: on mobile each row reflows into a label/value card */}
      <table className="tbl tbl-cards">
        <thead>
          <tr>
            <th>{labels.date}</th>
            <th>{t('document.list.table.counterparty_name')}</th>
            <th className="right">{labels.amount}</th>
            <th>{labels.category}</th>
            <th>{labels.status}</th>
            <th>{labels.uploaded}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {documents.map((doc) => (
            <tr key={doc.id}>
              <td className="mono" data-label={labels.date}>
                {formatDate(doc.transaction_date)}
                {doc.date_uncertain && <span className="faint"> *</span>}
              </td>
              <td className="cell-title">
                <span className="pri">{doc.counterparty_name}</span>
              </td>
              <td className="right tabular mono" data-label={labels.amount}>
                {formatJpy(doc.amount_cents, locale)}
              </td>
              <td data-label={labels.category}>{t(`document.category.${doc.category}`)}</td>
              <td data-label={labels.status}>
                <span
                  className={doc.status === 'voided' ? 'badge badge-danger' : 'badge badge-success'}
                >
                  {t(`document.status.${doc.status}`)}
                </span>
              </td>
              <td className="muted mono" data-label={labels.uploaded}>
                {doc.uploaded_at.slice(0, 10)}
              </td>
              <td className="right" data-label={labels.actions}>
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
