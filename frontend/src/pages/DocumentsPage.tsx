import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocumentSearch, DocumentSearchForm, DocumentTable } from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell } from '@/shared/ui/components/AppShell';
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
    <AppShell onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="page-head">
        <div className="titlebar">
          <span className="eyebrow">{t('navigation.documents')}</span>
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
            offset={pagination.offset}
            limit={pagination.limit}
            total={pagination.total}
            canPrev={pagination.canPrev}
            canNext={pagination.canNext}
            onPrev={pagination.goPrev}
            onNext={pagination.goNext}
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
    </AppShell>
  );
}
