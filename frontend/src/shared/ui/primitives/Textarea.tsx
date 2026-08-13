import { forwardRef, type TextareaHTMLAttributes } from 'react';

import { FIELD_BASE, FIELD_PLACEHOLDER } from './fieldBase';

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

// forwardRef so React Hook Form's register() can attach its ref.
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { className, rows = 3, ...rest },
  ref,
) {
  // `.textarea` added min-height/resize/line-height on top of the shared base.
  return (
    <textarea
      ref={ref}
      rows={rows}
      className={`${FIELD_BASE} ${FIELD_PLACEHOLDER} min-h-22 resize-y leading-field ${className ?? ''}`}
      {...rest}
    />
  );
});
