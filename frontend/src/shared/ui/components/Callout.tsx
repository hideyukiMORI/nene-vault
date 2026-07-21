import type { ReactNode } from 'react';

export interface CalloutProps {
  /** Visual tone of the callout. */
  tone: 'danger' | 'warn';
  children: ReactNode;
}

// Tone colours regenerated from the retired `.callout-danger`/`.callout-warn`
// blocks (C5 W3). NOTE: those component classes were dropped in drain #4 (#316)
// while this file still referenced them via a `callout-${tone}` template literal
// — the tone colours had silently regressed since #316 (same template-literal
// blind spot as 判例 #313). Keeping the tone as a variant map restores them and
// removes the dead component-class reference.
const TONE_CLASS: Record<CalloutProps['tone'], string> = {
  danger: 'bg-danger-soft border-danger text-x-danger-hover',
  warn: 'bg-warn-soft border-warn text-on-warn',
};

/**
 * Inline message block (error / warning). Base layout is utility-first; the tone
 * supplies the border/background/text colour.
 */
export function Callout({ tone, children }: CalloutProps) {
  return (
    <div
      className={`flex gap-2.75 rounded-md px-3.75 py-3.25 text-sm leading-diff border ${TONE_CLASS[tone]}`}
    >
      {children}
    </div>
  );
}
