import type { ReactNode } from 'react';

export interface ModalProps {
  /** Optional header title. When set, a bordered header with a close button is rendered. */
  title?: ReactNode;
  onClose: () => void;
  children: ReactNode;
  /** Panel max width: 'sm' (432) or 'md'/'lg' (520, default). */
  size?: 'sm' | 'md' | 'lg';
  /**
   * Resolved aria-label for the close button. Required in practice whenever
   * `title` is set (the close button only renders with a header); the consumer
   * supplies the already-translated string (fleet 会議R1②).
   */
  closeLabel?: string;
}

/**
 * Centered modal dialog: fixed overlay + framed panel, with an optional header
 * (title + close button). Forms inside supply their own `.modal-body` padding.
 */
export function Modal({ title, onClose, children, size = 'sm', closeLabel }: ModalProps) {
  const hasHeader = title !== undefined && title !== null;
  const panelClass = size === 'sm' ? 'modal modal-sm' : 'modal';

  return (
    <div role="dialog" aria-modal="true" className="modal-overlay">
      <div className={panelClass}>
        {hasHeader && (
          <div className="modal-header">
            <h2>{title}</h2>
            <button type="button" onClick={onClose} aria-label={closeLabel} className="modal-close">
              ✕
            </button>
          </div>
        )}
        {children}
      </div>
    </div>
  );
}
