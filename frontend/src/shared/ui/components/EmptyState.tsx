import type { ReactNode } from 'react';

export interface EmptyStateProps {
  children: ReactNode;
}

/**
 * Centred placeholder block for the empty and loading states of a data screen,
 * styled by the design-system `.empty-state` block.
 */
export function EmptyState({ children }: EmptyStateProps) {
  return <div className="empty-state">{children}</div>;
}
