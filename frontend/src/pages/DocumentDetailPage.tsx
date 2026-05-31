import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useDocumentById, useOcrSuggest } from '@/entities/document';
import { useDocumentHistory } from '@/entities/audit';
import { authStore } from '@/entities/auth';
import {
  VoidModal,
  RestoreModal,
  MetadataEditModal,
  DocumentHistoryTable,
} from '@/features/document-detail';
import type { OcrPrefill } from '@/features/document-detail';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate, formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Stack, Text } from '@/shared/ui';
import { env } from '@/shared/config/env';

type Modal = 'void' | 'restore' | 'metadata-edit' | null;

export function DocumentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { t, locale } = useTranslation();
  const navigate = useNavigate();
  const [modal, setModal] = useState<Modal>(null);
  const [ocrPrefill, setOcrPrefill] = useState<OcrPrefill | undefined>(undefined);
  const { suggest: ocrSuggest, isLoading: ocrLoading } = useOcrSuggest();

  const docId = id ?? '';
  const { data: doc, isLoading, isError } = useDocumentById(docId);
  const { data: history } = useDocumentHistory(docId);

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

  function handleDownload() {
    if (doc === undefined) return;
    const base = env.apiBaseUrl.replace(/\/$/, '');
    const url = `${base}/admin/vault/documents/${doc.id}/versions/${String(doc.version_number)}/download`;
    const a = document.createElement('a');
    a.href = url;
    a.download = doc.original_filename ?? `document-${doc.id}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  return (
    <AppShell onLogout={handleLogout}>
      <div className="max-w-4xl">
        <div className="mb-stack-md">
          <button
            type="button"
            onClick={() => {
              navigate('/documents');
            }}
            className="text-brand text-body-sm hover:underline"
          >
            ← {t('navigation.documents')}
          </button>
        </div>

        {isLoading && <Text tone="muted">{t('common.status.loading')}</Text>}
        {isError && <Text tone="danger">{t('common.status.error')}</Text>}

        {doc !== undefined && (
          <Stack gap="lg">
            <div className="flex items-start justify-between">
              <div>
                <Text as="h1" className="text-heading-md">
                  {doc.counterparty_name}
                </Text>
                <div className="mt-stack-xs flex items-center gap-inline-sm">
                  <span
                    className={
                      doc.status === 'voided'
                        ? 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-danger-muted text-danger'
                        : 'inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-success-muted text-success'
                    }
                  >
                    {t(`document.status.${doc.status}`)}
                  </span>
                  {doc.date_uncertain && (
                    <span className="inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-warning-muted text-warning">
                      {t('document.detail.date_uncertain_badge')}
                    </span>
                  )}
                  {!doc.is_metadata_confirmed && (
                    <span className="inline-flex rounded-full px-inline-sm py-stack-xs text-label-xs bg-muted-bg text-muted">
                      {t('document.detail.metadata_unconfirmed_badge')}
                    </span>
                  )}
                </div>
              </div>

              <div className="flex gap-inline-md">
                <Button variant="secondary" onClick={handleDownload}>
                  {t('document.detail.download_button')}
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => {
                    void handleOcrSuggest();
                  }}
                  disabled={ocrLoading}
                >
                  {ocrLoading
                    ? t('common.status.loading')
                    : t('document.detail.ocr_suggest_button')}
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

            <section className="rounded-lg border border-border bg-surface p-stack-md">
              <Text as="h2" className="mb-stack-md text-heading-sm">
                {t('document.detail.metadata_section')}
              </Text>
              <dl className="grid grid-cols-2 gap-x-inline-lg gap-y-stack-sm text-body-sm">
                <div>
                  <dt className="text-label-sm text-muted">
                    {t('document.metadata.transaction_date')}
                  </dt>
                  <dd className="mt-stack-xs">{formatDate(doc.transaction_date)}</dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">
                    {t('document.metadata.amount_cents')}
                  </dt>
                  <dd className="mt-stack-xs tabular-nums">
                    {formatJpy(doc.amount_cents, locale)}
                  </dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">{t('document.metadata.category')}</dt>
                  <dd className="mt-stack-xs">{t(`document.category.${doc.category}`)}</dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">{t('document.metadata.source')}</dt>
                  <dd className="mt-stack-xs">{t(`document.source.${doc.source}`)}</dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">{t('document.metadata.uploaded_at')}</dt>
                  <dd className="mt-stack-xs">{formatDateTime(doc.uploaded_at, locale)}</dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">
                    {t('document.metadata.retention_expires_at')}
                  </dt>
                  <dd className="mt-stack-xs">{formatDate(doc.retention_expires_at)}</dd>
                </div>
                {doc.tags.length > 0 && (
                  <div className="col-span-2">
                    <dt className="text-label-sm text-muted">{t('document.metadata.tags')}</dt>
                    <dd className="mt-stack-xs flex flex-wrap gap-inline-sm">
                      {doc.tags.map((tag) => (
                        <span
                          key={tag}
                          className="rounded-full bg-surface-raised px-inline-sm py-stack-xs text-label-xs"
                        >
                          {tag}
                        </span>
                      ))}
                    </dd>
                  </div>
                )}
              </dl>
            </section>

            <section className="rounded-lg border border-border bg-surface p-stack-md">
              <Text as="h2" className="mb-stack-md text-heading-sm">
                {t('document.detail.file_section')}
              </Text>
              <dl className="grid grid-cols-2 gap-x-inline-lg gap-y-stack-sm text-body-sm">
                <div>
                  <dt className="text-label-sm text-muted">
                    {t('document.metadata.version_number')}
                  </dt>
                  <dd className="mt-stack-xs">{doc.version_number}</dd>
                </div>
                <div>
                  <dt className="text-label-sm text-muted">{t('document.metadata.file_sha256')}</dt>
                  <dd className="mt-stack-xs font-mono text-label-xs break-all">
                    {doc.file_sha256}
                  </dd>
                </div>
              </dl>
            </section>

            <section className="rounded-lg border border-border bg-surface p-stack-md">
              <Text as="h2" className="mb-stack-md text-heading-sm">
                {t('document.history.title')}
              </Text>
              <DocumentHistoryTable events={history?.audit_events ?? []} />
            </section>
          </Stack>
        )}
      </div>

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
