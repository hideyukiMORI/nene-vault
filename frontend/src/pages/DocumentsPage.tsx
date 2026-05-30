import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  useDocumentSearch,
  DocumentSearchForm,
  DocumentTable,
  Pagination,
} from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Stack, Text } from '@/shared/ui';
import { LanguageSwitcher } from '@/shared/ui/components/LanguageSwitcher';
import { authStore } from '@/entities/auth';

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
    <div className="min-h-screen bg-surface">
      <header className="flex items-center gap-inline-lg border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
        <Text as="span" className="text-heading-sm">
          NeNe Vault
        </Text>
        <nav className="flex gap-inline-md">
          <Text as="span" className="font-medium text-brand">
            {t('navigation.documents')}
          </Text>
        </nav>
        <div className="ml-auto flex items-center gap-inline-md">
          <LanguageSwitcher />
          <Button variant="secondary" onClick={handleLogout}>
            {t('navigation.logout')}
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-inline-lg py-stack-lg">
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
      </main>

      {showUpload && (
        <DocumentUploadModal
          onClose={() => {
            setShowUpload(false);
          }}
        />
      )}
    </div>
  );
}
