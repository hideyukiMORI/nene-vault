import { forwardRef, type SelectHTMLAttributes } from 'react';

import { FIELD_BASE } from './fieldBase';

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

// forwardRef so React Hook Form's register() can attach its ref.
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { className, children, ...rest },
  ref,
) {
  return (
    // `select-chevron` carries the data-URI arrow that no utility can express;
    // `pr-7.5` (30px) keeps the text clear of it.
    <select
      ref={ref}
      className={`${FIELD_BASE} select-chevron pr-7.5 ${className ?? ''}`}
      {...rest}
    >
      {children}
    </select>
  );
});
