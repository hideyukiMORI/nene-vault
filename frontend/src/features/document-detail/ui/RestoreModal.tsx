import { useRestoreDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';
import { Modal } from '@/shared/ui/components/Modal';

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
    <Modal
      title={t('document.restore.title')}
      onClose={onClose}
      closeLabel={t('common.buttons.close')}
    >
      <div className="modal-body space-y-4">
        <p className="text-text-muted body-sm">{t('document.restore.description')}</p>
        {submitError !== null && <p className="text-2xs text-danger">{t(submitError)}</p>}
        <div className="flex items-center justify-end gap-2 max-md:flex-col-reverse max-md:items-stretch max-md:gap-2.5">
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
