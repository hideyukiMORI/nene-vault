import { useRestoreDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Modal } from '@/shared/ui';

interface RestoreModalProps {
  documentId: string;
  onClose: () => void;
}

export function RestoreModal({ documentId, onClose }: RestoreModalProps) {
  const { t } = useTranslation();
  const mutation = useRestoreDocument(onClose);
  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return (
    <Modal title={t('document.restore.title')} onClose={onClose}>
      <div className="modal-body stack-md">
        <p className="muted body-sm">{t('document.restore.description')}</p>
        {submitError !== null && <p className="field-error">{t(submitError)}</p>}
        <div className="row end gap-sm">
          <Button type="button" variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.buttons.cancel')}
          </Button>
          <Button
            type="button"
            variant="primary"
            disabled={mutation.isPending}
            onClick={() => {
              mutation.mutate(documentId);
            }}
          >
            {mutation.isPending
              ? t('common.status.processing')
              : t('document.restore.confirm_button')}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
