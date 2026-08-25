import {
  Button,
  Card,
  EmptyState,
  Icon,
  InlineAlert,
  Pagination,
  Stack,
} from '@hideyukimori/nene2-ui';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocumentSearch, DocumentSearchForm, DocumentTable } from '@/features/document-search';
import { DocumentUploadModal } from '@/features/document-upload';
import { authStore } from '@/shared/api/auth-session';
import { roleHasCapability } from '@/shared/auth/capabilities';
import { useTranslation } from '@/shared/i18n/use-translation';
import { PAGINATION_CHROME } from '@/shared/ui/primitives/paginationChrome';
import { AppChrome } from '@/features/app-chrome';

export function DocumentsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  // #451: a viewer saw the button and got a 403 on click. The gate mirrors the backend's
  // CapabilityResolver (viewer = ViewDocuments only), the same way the rail and HomePage do.
  const canUpload = roleHasCapability(session?.role, 'UploadDocument');
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
        {/* No layout className here. Until 0.13.0 the kit's Button set no display, so an icon
            and a label sat on the text baseline with nothing between them and this call site
            carried `inline-flex items-center gap-x-2xs` itself (#398). The kit lays out its
            own children now; the gap is `--spacing-x-slot-button-gap` in the theme. */}
        {canUpload && (
          <Button
            variant="primary"
            onClick={() => {
              setShowUpload(true);
            }}
          >
            <Icon
              decorative
              size="sm"
              strokeWidth={1.8}
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M12 19V6" />
              <path d="m6 11 6-6 6 6" />
              <path d="M5 20h14" />
            </Icon>
            {t('document.list.upload_button')}
          </Button>
        )}
      </div>

      <DocumentSearchForm form={form} onSubmit={onSubmit} onReset={onReset} isLoading={isLoading} />

      {isError && <InlineAlert tone="danger">{t('common.status.error')}</InlineAlert>}

      {isLoading ? (
        <EmptyState message={t('common.status.loading')} />
      ) : (
        <Card pad="none">
          <DocumentTable
            documents={documents}
            onSelectDocument={(id) => {
              void navigate(`/documents/${id}`);
            }}
          />
          {pagination.total > 0 && (
            <Pagination
              label={t('common.pagination.label')}
              className={PAGINATION_CHROME}
              size="sm"
              statusPlacement="start"
              canPrev={pagination.canPrev}
              canNext={pagination.canNext}
              onPrev={pagination.goPrev}
              onNext={pagination.goNext}
              status={t('common.pagination.showing', {
                from: String(pagination.offset + 1),
                to: String(Math.min(pagination.offset + pagination.limit, pagination.total)),
                total: String(pagination.total),
              })}
              previousLabel={t('common.buttons.previous')}
              nextLabel={t('common.buttons.next')}
            />
          )}
        </Card>
      )}

      {canUpload && showUpload && (
        <DocumentUploadModal
          onClose={() => {
            setShowUpload(false);
          }}
        />
      )}
    </AppChrome>
  );
}
