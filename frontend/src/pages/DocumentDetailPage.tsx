import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  useDocumentById,
  useDocumentFile,
  fetchDocumentBlob,
  useOcrSuggest,
} from '@/entities/document';
import { useDocumentHistory } from '@/entities/audit';
import { authStore } from '@/entities/auth';
import {
  VoidModal,
  RestoreModal,
  MetadataEditModal,
  DocumentHistoryTable,
  DocumentPreview,
} from '@/features/document-detail';
import type { OcrPrefill } from '@/features/document-detail';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate, formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Callout, EmptyState } from '@/shared/ui';

type Modal = 'void' | 'restore' | 'metadata-edit' | null;

export function DocumentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { t, locale } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  const [modal, setModal] = useState<Modal>(null);
  const [ocrPrefill, setOcrPrefill] = useState<OcrPrefill | undefined>(undefined);
  const { suggest: ocrSuggest, isLoading: ocrLoading } = useOcrSuggest();

  const docId = id ?? '';
  const { data: doc, isLoading, isError } = useDocumentById(docId);
  const { data: history } = useDocumentHistory(docId);

  // The download/preview endpoint is keyed by the version's ULID, which only the
  // history response carries — the document detail exposes just the ordinal number.
  const currentVersion =
    doc !== undefined
      ? history?.versions.find((v) => v.version_number === doc.version_number)
      : undefined;
  const file = useDocumentFile(
    doc !== undefined && currentVersion !== undefined
      ? {
          documentId: doc.id,
          versionId: currentVersion.id,
          sha256: currentVersion.file_sha256,
          mimeType: currentVersion.mime_type,
        }
      : undefined,
  );

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  async function handleOcrSuggest() {
    if (doc === undefined) return;
    const prefill = await ocrSuggest(doc.id);
    if (prefill !== null) {
      setOcrPrefill(prefill);
    }
    setModal('metadata-edit');
  }

  async function handleDownload() {
    if (doc === undefined || currentVersion === undefined) return;
    // Reuse the already-fetched, integrity-verified bytes when available; otherwise
    // fetch through the authenticated client (a plain <a href> would drop the bearer token).
    const blob = file.blob ?? (await fetchDocumentBlob(doc.id, currentVersion.id));
    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = objectUrl;
    a.download = doc.original_filename ?? `document-${doc.id}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(objectUrl);
  }

  return (
    <AppShell
      onLogout={handleLogout}
      userEmail={session?.email}
      userRole={session?.role}
      width="mid"
    >
      <button
        type="button"
        className="link"
        onClick={() => {
          navigate('/documents');
        }}
      >
        ← {t('navigation.documents')}
      </button>

      {isLoading && <EmptyState>{t('common.status.loading')}</EmptyState>}
      {isError && <Callout tone="danger">{t('common.status.error')}</Callout>}

      {doc !== undefined && (
        <>
          <div className="page-head">
            <div className="titlebar">
              <span className="eyebrow">{t('document.detail.title')}</span>
              <h1 className="page-title">{doc.counterparty_name}</h1>
              <div className="row gap-sm wrap">
                <span
                  className={doc.status === 'voided' ? 'badge badge-danger' : 'badge badge-success'}
                >
                  {t(`document.status.${doc.status}`)}
                </span>
                {doc.date_uncertain && (
                  <span className="badge badge-warning">
                    {t('document.detail.date_uncertain_badge')}
                  </span>
                )}
                {!doc.is_metadata_confirmed && (
                  <span className="badge badge-muted">
                    {t('document.detail.metadata_unconfirmed_badge')}
                  </span>
                )}
              </div>
            </div>

            <div className="row gap-sm wrap">
              <Button
                variant="secondary"
                onClick={() => {
                  void handleDownload();
                }}
                disabled={currentVersion === undefined}
              >
                {t('document.detail.download_button')}
              </Button>
              <Button
                variant="secondary"
                onClick={() => {
                  void handleOcrSuggest();
                }}
                disabled={ocrLoading}
              >
                {ocrLoading ? t('common.status.loading') : t('document.detail.ocr_suggest_button')}
              </Button>
              <Button
                variant="secondary"
                onClick={() => {
                  setOcrPrefill(undefined);
                  setModal('metadata-edit');
                }}
              >
                {t('common.buttons.edit')}
              </Button>
              {doc.status === 'active' ? (
                <Button
                  variant="danger"
                  onClick={() => {
                    setModal('void');
                  }}
                >
                  {t('common.buttons.void')}
                </Button>
              ) : (
                <Button
                  variant="secondary"
                  onClick={() => {
                    setModal('restore');
                  }}
                >
                  {t('common.buttons.restore')}
                </Button>
              )}
            </div>
          </div>

          <section className="card p-md">
            <div className="row gap-sm mb-stack-md">
              <span className="tick" />
              <h2 className="subtitle">{t('document.detail.metadata_section')}</h2>
            </div>
            <dl className="dl">
              <div>
                <dt>{t('document.metadata.transaction_date')}</dt>
                <dd className="mono">{formatDate(doc.transaction_date)}</dd>
              </div>
              <div>
                <dt>{t('document.metadata.amount_cents')}</dt>
                <dd className="mono tabular">{formatJpy(doc.amount_cents, locale)}</dd>
              </div>
              <div>
                <dt>{t('document.metadata.category')}</dt>
                <dd>{t(`document.category.${doc.category}`)}</dd>
              </div>
              <div>
                <dt>{t('document.metadata.source')}</dt>
                <dd>{t(`document.source.${doc.source}`)}</dd>
              </div>
              <div>
                <dt>{t('document.metadata.uploaded_at')}</dt>
                <dd className="mono">{formatDateTime(doc.uploaded_at, locale)}</dd>
              </div>
              <div>
                <dt>{t('document.metadata.retention_expires_at')}</dt>
                <dd className="mono">{formatDate(doc.retention_expires_at)}</dd>
              </div>
              {doc.tags.length > 0 && (
                <div className="col2">
                  <dt>{t('document.metadata.tags')}</dt>
                  <dd className="row gap-sm wrap">
                    {doc.tags.map((tag) => (
                      <span key={tag} className="tag">
                        {tag}
                      </span>
                    ))}
                  </dd>
                </div>
              )}
            </dl>
          </section>

          <section className="card p-md">
            <div className="row gap-sm mb-stack-md">
              <span className="tick" />
              <h2 className="subtitle">{t('document.detail.file_section')}</h2>
            </div>
            <div className="preview-block">
              <DocumentPreview file={file} />
            </div>
            <dl className="dl">
              <div>
                <dt>{t('document.metadata.version_number')}</dt>
                <dd className="mono">{doc.version_number}</dd>
              </div>
              <div className="col2">
                <dt>{t('document.metadata.file_sha256')}</dt>
                <dd className="mono break-all">{doc.file_sha256}</dd>
              </div>
            </dl>
          </section>

          <section className="card p-md">
            <div className="row gap-sm mb-stack-md">
              <span className="tick" />
              <h2 className="subtitle">{t('document.history.title')}</h2>
            </div>
            <DocumentHistoryTable events={history?.audit_events ?? []} />
          </section>
        </>
      )}

      {modal === 'void' && doc !== undefined && (
        <VoidModal
          documentId={doc.id}
          onClose={() => {
            setModal(null);
          }}
        />
      )}
      {modal === 'restore' && doc !== undefined && (
        <RestoreModal
          documentId={doc.id}
          onClose={() => {
            setModal(null);
          }}
        />
      )}
      {modal === 'metadata-edit' && doc !== undefined && (
        <MetadataEditModal
          doc={doc}
          {...(ocrPrefill !== undefined && { ocrPrefill })}
          onClose={() => {
            setModal(null);
            setOcrPrefill(undefined);
          }}
        />
      )}
    </AppShell>
  );
}
