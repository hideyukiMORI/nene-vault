import { Button, EmptyState, Icon, InlineAlert, Stack } from '@hideyukimori/nene2-ui';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocumentSearch, DocumentSearchForm, DocumentTable } from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { authStore } from '@/shared/api/auth-session';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppChrome } from '@/features/app-chrome';
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
      <div className="flex items-end justify-between gap-4 max-md:flex-col max-md:items-start max-md:gap-3.5">
        <Stack gap="2xs">
          <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
            {t('navigation.documents')}
          </span>
          <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
            {t('document.list.title')}
          </h1>
        </Stack>
        {/* 🔴 `inline-flex items-center` and the gap are on the call site because the kit's
            Button does not lay out its own children — it sets no display, so an icon and a
            label sit on the text baseline with nothing between them. vault's own Button
            carried `inline-flex items-center justify-center gap-1.75`. Raised as #398; this
            className goes away when the kit takes it on. */}
        <Button
          variant="primary"
          className="inline-flex items-center gap-x-2xs"
          onClick={() => {
            setShowUpload(true);
          }}
        >
          <Icon decorative size="sm" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 19V6" />
            <path d="m6 11 6-6 6 6" />
            <path d="M5 20h14" />
          </Icon>
          {t('document.list.upload_button')}
        </Button>
      </div>

      <DocumentSearchForm form={form} onSubmit={onSubmit} onReset={onReset} isLoading={isLoading} />

      {isError && <InlineAlert tone="danger">{t('common.status.error')}</InlineAlert>}

      {isLoading ? (
        <EmptyState message={t('common.status.loading')} />
      ) : (
        <div className="card shadow-none">
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
