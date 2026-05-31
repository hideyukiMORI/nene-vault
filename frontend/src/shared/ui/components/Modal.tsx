import type { ReactNode } from 'react';
import { Text } from '@/shared/ui/primitives/Text';

export interface ModalProps {
  /** Optional header title. When set, a bordered header with a close button is rendered. */
  title?: ReactNode;
  onClose: () => void;
  children: ReactNode;
  /** Panel max width. */
  size?: 'sm' | 'md' | 'lg';
}

const SIZE_CLASS: Record<NonNullable<ModalProps['size']>, string> = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
};

/**
 * Centered modal dialog: fixed overlay + panel, with an optional header (title +
 * close button). Replaces the hand-rolled `role="dialog"` overlays across the app.
 *
 * The body padding differs by header presence to match the prior layouts:
 * with a header the children supply their own padding (forms use `p-inline-lg`);
 * without a header the panel pads the body directly.
 */
export function Modal({ title, onClose, children, size = 'sm' }: ModalProps) {
  const hasHeader = title !== undefined && title !== null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div
        className={`w-full ${SIZE_CLASS[size]} rounded-xl border border-border bg-surface shadow-lg`}
      >
        {hasHeader && (
          <div className="flex items-center justify-between border-b border-border px-inline-lg py-stack-md">
            <Text as="h2" className="text-heading-sm">
              {title}
            </Text>
            <button
              type="button"
              onClick={onClose}
              aria-label="Close"
              className="text-muted hover:text-foreground"
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
