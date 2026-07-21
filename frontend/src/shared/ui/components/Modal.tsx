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

// Panel width is picked per size as a full literal utility string (AM-13 variant
// map form) — `max-w-modal`=520px, `max-w-modal-sm`=432px; on mobile both collapse
// to a bottom sheet (`max-w-full`, top-rounded).
const PANEL_MD =
  'bg-surface-raised border border-x-line-mid rounded-lg shadow-lg w-full max-w-modal max-h-dialog overflow-auto max-md:max-w-full max-md:max-h-sheet max-md:rounded-t-lg max-md:rounded-b-none';
const PANEL_SM =
  'bg-surface-raised border border-x-line-mid rounded-lg shadow-lg w-full max-w-modal-sm max-h-dialog overflow-auto max-md:max-w-full max-md:max-h-sheet max-md:rounded-t-lg max-md:rounded-b-none';

/**
 * Centered modal dialog: fixed overlay + framed panel, with an optional header
 * (title + close button). Forms inside supply their own padding.
 */
export function Modal({ title, onClose, children, size = 'sm', closeLabel }: ModalProps) {
  const hasHeader = title !== undefined && title !== null;
  const panelClass = size === 'sm' ? PANEL_SM : PANEL_MD;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 bg-x-scrim/52 flex items-center justify-center p-6 z-modal max-md:items-end max-md:p-0"
    >
      <div className={panelClass}>
        {hasHeader && (
          <div className="flex items-center gap-2.5 justify-between px-5.5 py-4.25 border-b border-border">
            <h2 className="text-h2 font-semibold flex items-center gap-2.25">{title}</h2>
            <button
              type="button"
              onClick={onClose}
              aria-label={closeLabel}
              className="bg-transparent border-0 text-modal-close cursor-pointer text-text-faint leading-none px-1.5 py-0.5 rounded-sm hover:text-x-ink-deep hover:bg-surface-sunken"
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
