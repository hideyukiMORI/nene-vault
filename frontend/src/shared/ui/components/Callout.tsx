import type { ReactNode } from 'react';

export interface CalloutProps {
  /** Visual tone of the callout. */
  tone: 'danger' | 'warn';
  children: ReactNode;
}

/**
 * Inline message block (error / warning) styled by the design-system `.callout`
 * block. Replaces hand-written `callout callout-*` divs across pages and modals.
 */
export function Callout({ tone, children }: CalloutProps) {
  return <div className={`callout callout-${tone}`}>{children}</div>;
}
