import { Badge, DataTable, EmptyState, type DataColumn } from '@hideyukimori/nene2-ui';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate } from '@/shared/lib/format';
import { BADGE_DOT } from '@/shared/ui/primitives/badgeBase';
import type { VaultDocument } from '@/entities/document';

interface DocumentTableProps {
  documents: VaultDocument[];
  onSelectDocument: (id: string) => void;
}

/**
 * The received-documents list on the kit's `DataTable` (0.17.0, W1b). `collapse="sm"` reflows
 * each row into a label/value card below `sm`, which is what the retired `.tbl-cards` did with
 * `data-label` and `::before` — the kit now owns that mechanism (fleet #423).
 *
 * ⚠️ Whether the collapsed card reads the label twice (the `sr-only` header row plus the
 * `data-label` pseudo-element) is not measurable in jsdom; the live lane checks it (#412).
 */
export function DocumentTable({ documents, onSelectDocument }: DocumentTableProps) {
  const { t, locale } = useTranslation();

  if (documents.length === 0) {
    return <EmptyState message={t('document.list.empty')} />;
  }

  const columns: DataColumn<VaultDocument>[] = [
    {
      key: 'transaction_date',
      header: t('document.list.table.transaction_date'),
      cell: (doc) => (
        <span className="font-mono zero-slash">
          {formatDate(doc.transaction_date)}
          {doc.date_uncertain && <span className="text-text-faint"> *</span>}
        </span>
      ),
    },
    {
      key: 'counterparty_name',
      header: t('document.list.table.counterparty_name'),
      cell: (doc) => <span className="font-semibold text-x-ink-deep">{doc.counterparty_name}</span>,
    },
    {
      key: 'amount',
      header: t('document.list.table.amount'),
      align: 'end',
      cell: (doc) => (
        <span className="tabular-nums font-mono zero-slash">
          {formatJpy(doc.amount_cents, locale)}
        </span>
      ),
    },
    {
      key: 'category',
      header: t('document.list.table.category'),
      cell: (doc) => t(`document.category.${doc.category}`),
    },
    {
      key: 'status',
      header: t('document.list.table.status'),
      cell: (doc) => (
        <Badge tone={doc.status === 'voided' ? 'danger' : 'success'} className={BADGE_DOT}>
          {t(`document.status.${doc.status}`)}
        </Badge>
      ),
    },
    {
      key: 'uploaded_at',
      header: t('document.list.table.uploaded_at'),
      cell: (doc) => (
        <span className="text-text-muted font-mono zero-slash">{doc.uploaded_at.slice(0, 10)}</span>
      ),
    },
    {
      key: 'actions',
      header: t('document.list.table.actions'),
      align: 'end',
      cell: (doc) => (
        <button
          type="button"
          className="text-accent bg-none border-0 cursor-pointer text-sm leading-inherit no-underline hover:text-x-navy-deep hover:underline hover:underline-offset-2"
          onClick={() => {
            onSelectDocument(doc.id);
          }}
        >
          {t('common.buttons.view_detail')}
        </button>
      ),
    },
  ];

  return (
    <div className="overflow-x-auto">
      <DataTable
        columns={columns}
        rows={documents}
        rowKey={(doc) => doc.id}
        caption={t('document.list.title')}
        collapse="sm"
      />
    </div>
  );
}
