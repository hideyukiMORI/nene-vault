import {
  Badge,
  Button,
  Card,
  DetailList,
  EmptyState,
  InlineAlert,
  Stack,
} from '@hideyukimori/nene2-ui';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useDocumentById, fetchDocumentBlob, useOcrSuggest } from '@/entities/document';
import { useDocumentHistory } from '@/entities/audit';
import { authStore } from '@/shared/api/auth-session';
import { roleHasCapability } from '@/shared/auth/capabilities';
import {
  VoidModal,
  RestoreModal,
  MetadataEditModal,
  DocumentHistoryTable,
} from '@/features/document-detail';
import type { OcrPrefill } from '@/features/document-detail';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatJpy, formatDate, formatDateTime } from '@/shared/lib/format';
import { AppChrome } from '@/features/app-chrome';
import { BADGE_DOT } from '@/shared/ui/primitives/badgeBase';

type Modal = 'void' | 'restore' | 'metadata-edit' | null;

export function DocumentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { t, locale } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  // #451: mirror the backend capabilities so a viewer is not shown actions that 403.
  const canEdit = roleHasCapability(session?.role, 'EditMetadata');
  const canVoid = roleHasCapability(session?.role, 'VoidDocument');
  const [modal, setModal] = useState<Modal>(null);
  const [ocrPrefill, setOcrPrefill] = useState<OcrPrefill | undefined>(undefined);
  const { suggest: ocrSuggest, isLoading: ocrLoading } = useOcrSuggest();

  const docId = id ?? '';
  const { data: doc, isLoading, isError } = useDocumentById(docId);
  const { data: history } = useDocumentHistory(docId);

  // The download endpoint is keyed by the version's ULID, which only the
  // history response carries — the document detail exposes just the ordinal
  // version_number (#179).
  const currentVersion =
    doc !== undefined
      ? history?.versions.find((v) => v.version_number === doc.version_number)
      : undefined;

  function handleLogout() {
    authStore.clearSession();
    void navigate('/login', { replace: true });
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
    // Fetch through the authenticated client: a plain <a href> would drop the
    // bearer token (the backend is JWT-only, no session cookie), and the route
    // is keyed by the version ULID, not the ordinal version_number (#179).
    const blob = await fetchDocumentBlob(doc.id, currentVersion.id);
    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = objectUrl;
    a.download = doc.original_filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(objectUrl);
  }

  return (
    <AppChrome
      onLogout={handleLogout}
      userEmail={session?.email}
      userRole={session?.role}
      width="mid"
    >
      <button
        type="button"
        className="text-accent bg-none border-0 cursor-pointer text-sm leading-inherit no-underline hover:text-x-navy-deep hover:underline hover:underline-offset-2"
        onClick={() => {
          void navigate('/documents');
        }}
      >
        ← {t('navigation.documents')}
      </button>

      {isLoading && <EmptyState message={t('common.status.loading')} />}
      {isError && <InlineAlert tone="danger">{t('common.status.error')}</InlineAlert>}

      {doc !== undefined && (
        <>
          <div className="flex items-end justify-between gap-4 max-md:flex-col max-md:items-start max-md:gap-3.5">
            <Stack gap="2xs">
              <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
                {t('document.detail.title')}
              </span>
              <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
                {doc.counterparty_name}
              </h1>
              <Stack direction="horizontal" align="center" wrap gap="2xs">
                <Badge tone={doc.status === 'voided' ? 'danger' : 'success'} className={BADGE_DOT}>
                  {t(`document.status.${doc.status}`)}
                </Badge>
                {doc.date_uncertain && (
                  <Badge tone="warn" className={BADGE_DOT}>
                    {t('document.detail.date_uncertain_badge')}
                  </Badge>
                )}
                {!doc.is_metadata_confirmed && (
                  <Badge tone="neutral" className={BADGE_DOT}>
                    {t('document.detail.metadata_unconfirmed_badge')}
                  </Badge>
                )}
              </Stack>
            </Stack>

            <Stack direction="horizontal" align="center" wrap gap="2xs">
              <Button
                variant="secondary"
                onClick={() => {
                  void handleDownload();
                }}
                disabled={currentVersion === undefined}
              >
                {t('document.detail.download_button')}
              </Button>
              {canEdit && (
                <>
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
                </>
              )}
              {canVoid &&
                (doc.status === 'active' ? (
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
                ))}
            </Stack>
          </div>

          <Card raised pad="md">
            <div className="flex items-center gap-2 mb-stack-md">
              <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
              <h2 className="text-h2 font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25">
                {t('document.detail.metadata_section')}
              </h2>
            </div>
            <DetailList
              layout="columns"
              rows={[
                {
                  label: t('document.metadata.transaction_date'),
                  value: (
                    <span className="font-mono zero-slash">{formatDate(doc.transaction_date)}</span>
                  ),
                },
                {
                  label: t('document.metadata.amount_cents'),
                  value: (
                    <span className="font-mono zero-slash tabular-nums">
                      {formatJpy(doc.amount_cents, locale)}
                    </span>
                  ),
                },
                {
                  label: t('document.metadata.category'),
                  value: t(`document.category.${doc.category}`),
                },
                { label: t('document.metadata.source'), value: t(`document.source.${doc.source}`) },
                {
                  label: t('document.metadata.uploaded_at'),
                  value: (
                    <span className="font-mono zero-slash">
                      {formatDateTime(doc.uploaded_at, locale)}
                    </span>
                  ),
                },
                {
                  label: t('document.metadata.retention_expires_at'),
                  value: (
                    <span className="font-mono zero-slash">
                      {formatDate(doc.retention_expires_at)}
                    </span>
                  ),
                },
              ]}
            />
            {doc.tags.length > 0 && (
              // Full-width row: the kit has no column span, so a one-row stacked list follows the grid.
              <DetailList
                rows={[
                  {
                    label: t('document.metadata.tags'),
                    value: (
                      <span className="flex items-center gap-2 flex-wrap">
                        {doc.tags.map((tag) => (
                          <span
                            key={tag}
                            className="inline-flex rounded-sm bg-surface-overlay border border-x-line-mid px-2.25 py-0.75 text-2xs text-text-muted"
                          >
                            {tag}
                          </span>
                        ))}
                      </span>
                    ),
                  },
                ]}
              />
            )}
          </Card>

          <Card raised pad="md">
            <div className="flex items-center gap-2 mb-stack-md">
              <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
              <h2 className="text-h2 font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25">
                {t('document.detail.file_section')}
              </h2>
            </div>
            <DetailList
              layout="columns"
              rows={[
                {
                  label: t('document.metadata.version_number'),
                  value: <span className="font-mono zero-slash">{doc.version_number}</span>,
                },
              ]}
            />
            {/* Full-width row: a 64-char hash needs the whole width; the kit has no column span. */}
            <DetailList
              rows={[
                {
                  label: t('document.metadata.file_sha256'),
                  value: <span className="font-mono zero-slash break-all">{doc.file_sha256}</span>,
                },
              ]}
            />
          </Card>

          <Card raised pad="md">
            <div className="flex items-center gap-2 mb-stack-md">
              <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
              <h2 className="text-h2 font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25">
                {t('document.history.title')}
              </h2>
            </div>
            <DocumentHistoryTable events={history?.audit_events ?? []} />
          </Card>
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
    </AppChrome>
  );
}
