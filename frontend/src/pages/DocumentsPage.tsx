import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocumentSearch, DocumentSearchForm, DocumentTable } from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell, Button, Pagination, Stack, Text } from '@/shared/ui';

export function DocumentsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [showUpload, setShowUpload] = useState(false);

  const { form, onSubmit, onReset, result, pagination } = useDocumentSearch();

  const documents = result.data?.items ?? [];
  const isLoading = result.isLoading;
  const isError = result.isError;

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  return (
    <AppShell onLogout={handleLogout}>
      <Stack gap="lg">
        <div className="flex items-center justify-between">
          <Text as="h1" className="text-heading-md">
            {t('document.list.title')}
          </Text>
          <Button
            variant="primary"
            onClick={() => {
              setShowUpload(true);
            }}
          >
            {t('document.list.upload_button')}
          </Button>
        </div>

        <DocumentSearchForm
          form={form}
          onSubmit={onSubmit}
          onReset={onReset}
          isLoading={isLoading}
        />

        {isError && <Text tone="danger">{t('common.status.error')}</Text>}

        {isLoading ? (
          <Text tone="muted">{t('common.status.loading')}</Text>
        ) : (
          <div className="rounded-lg border border-border bg-surface">
            <DocumentTable
              documents={documents}
              onSelectDocument={(id) => {
                navigate(`/documents/${id}`);
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
      </Stack>

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
