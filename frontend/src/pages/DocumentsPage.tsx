import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocumentSearch, DocumentSearchForm, DocumentTable } from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { authStore } from '@/shared/api/auth-session';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppChrome } from '@/features/app-chrome';
import { Button } from '@/shared/ui/primitives/Button';
import { Callout } from '@/shared/ui/components/Callout';
import { EmptyState } from '@/shared/ui/components/EmptyState';
import { Pagination } from '@/shared/ui/components/Pagination';

export function DocumentsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  const [showUpload, setShowUpload] = useState(false);

  const { form, onSubmit, onReset, result, pagination } = useDocumentSearch();

  const documents = result.data?.items ?? [];
  const isLoading = result.isLoading;
  const isError = result.isError;

  function handleLogout() {
    authStore.clearSession();
    void navigate('/login', { replace: true });
  }

  return (
    <AppChrome onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="page-head">
        <div className="titlebar">
          <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
            {t('navigation.documents')}
          </span>
          <h1 className="page-title">{t('document.list.title')}</h1>
        </div>
        <Button
          variant="primary"
          onClick={() => {
            setShowUpload(true);
          }}
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M12 19V6" />
            <path d="m6 11 6-6 6 6" />
            <path d="M5 20h14" />
          </svg>
          {t('document.list.upload_button')}
        </Button>
      </div>

      <DocumentSearchForm form={form} onSubmit={onSubmit} onReset={onReset} isLoading={isLoading} />

      {isError && <Callout tone="danger">{t('common.status.error')}</Callout>}

      {isLoading ? (
        <EmptyState>{t('common.status.loading')}</EmptyState>
      ) : (
        <div className="card flush">
          <DocumentTable
            documents={documents}
            onSelectDocument={(id) => {
              void navigate(`/documents/${id}`);
            }}
          />
          <Pagination
            total={pagination.total}
            canPrev={pagination.canPrev}
            canNext={pagination.canNext}
            onPrev={pagination.goPrev}
            onNext={pagination.goNext}
            showingLabel={t('common.pagination.showing', {
              from: String(pagination.offset + 1),
              to: String(Math.min(pagination.offset + pagination.limit, pagination.total)),
              total: String(pagination.total),
            })}
            previousLabel={t('common.buttons.previous')}
            nextLabel={t('common.buttons.next')}
          />
        </div>
      )}

      {showUpload && (
        <DocumentUploadModal
          onClose={() => {
            setShowUpload(false);
          }}
        />
      )}
    </AppChrome>
  );
}
