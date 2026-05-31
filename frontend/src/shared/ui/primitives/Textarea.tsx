import { forwardRef, type TextareaHTMLAttributes } from 'react';

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

// forwardRef so React Hook Form's register() can attach its ref. Styling matches
// the prior ad-hoc textarea so the refactor is visually identical.
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { className, rows = 3, ...rest },
  ref,
) {
  return (
    <textarea
      ref={ref}
      rows={rows}
      className={`rounded-md border border-border bg-surface px-inline-sm py-stack-xs text-body-sm focus:outline-none focus:ring-2 focus:ring-brand resize-none ${className ?? ''}`}
      {...rest}
    />
  );
});
