import { useTranslation } from '@/shared/i18n/use-translation';
import { Callout } from '@/shared/ui';
import type { DocumentFile } from '@/entities/document';

const PREVIEWABLE_IMAGE_TYPES = ['image/jpeg', 'image/png'];

interface DocumentPreviewProps {
  file: DocumentFile;
}

export function DocumentPreview({ file }: DocumentPreviewProps) {
  const { t } = useTranslation();

  if (file.status === 'loading') {
    return <div className="preview-frame preview-empty">{t('document.preview.loading')}</div>;
  }

  if (file.status === 'error' || file.url === undefined) {
    return <Callout tone="danger">{t('document.preview.error')}</Callout>;
  }

  // Integrity check failed — do NOT render the bytes; the file may be tampered.
  if (file.status === 'mismatch') {
    return <Callout tone="danger">{t('document.preview.integrity_mismatch')}</Callout>;
  }

  return (
    <>
      <div className="row gap-sm mb-stack-md">
        <span className="badge badge-success no-dot">
          ✓ {t('document.preview.integrity_verified')}
        </span>
      </div>
      {PREVIEWABLE_IMAGE_TYPES.includes(file.mimeType) ? (
        <img className="preview-frame" src={file.url} alt={t('document.preview.image_alt')} />
      ) : file.mimeType === 'application/pdf' ? (
        <iframe
          className="preview-frame preview-pdf"
          src={file.url}
          title={t('document.preview.pdf_title')}
        />
      ) : (
        <div className="preview-frame preview-empty">{t('document.preview.unsupported')}</div>
      )}
    </>
  );
}
