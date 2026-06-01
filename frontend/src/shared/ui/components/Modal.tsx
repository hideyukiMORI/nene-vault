import type { ReactNode } from 'react';
import { useTranslation } from '@/shared/i18n/use-translation';

export interface ModalProps {
  /** Optional header title. When set, a bordered header with a close button is rendered. */
  title?: ReactNode;
  onClose: () => void;
  children: ReactNode;
  /** Panel max width: 'sm' (432) or 'md'/'lg' (520, default). */
  size?: 'sm' | 'md' | 'lg';
}

/**
 * Centered modal dialog: fixed overlay + framed panel, with an optional header
 * (title + close button). Forms inside supply their own `.modal-body` padding.
 */
export function Modal({ title, onClose, children, size = 'sm' }: ModalProps) {
  const { t } = useTranslation();
  const hasHeader = title !== undefined && title !== null;
  const panelClass = size === 'sm' ? 'modal modal-sm' : 'modal';

  return (
    <div role="dialog" aria-modal="true" className="modal-overlay">
      <div className={panelClass}>
        {hasHeader && (
          <div className="modal-header">
            <h2>{title}</h2>
            <button
              type="button"
              onClick={onClose}
              aria-label={t('common.buttons.close')}
              className="modal-close"
            >
              ✕
            </button>
          </div>
        )}
        {children}
      </div>
    </div>
  );
}
