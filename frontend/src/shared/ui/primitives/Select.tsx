import { forwardRef, type SelectHTMLAttributes } from 'react';

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

// forwardRef so React Hook Form's register() can attach its ref. Styling matches
// the prior ad-hoc selects so the refactor is visually identical.
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { className, children, ...rest },
  ref,
) {
  return (
    <select
      ref={ref}
      className={`h-10 rounded-md border border-border bg-surface px-inline-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-brand ${className ?? ''}`}
      {...rest}
    >
      {children}
    </select>
  );
});
