import { forwardRef, type SelectHTMLAttributes } from 'react';

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

// forwardRef so React Hook Form's register() can attach its ref.
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { className, children, ...rest },
  ref,
) {
  return (
    <select ref={ref} className={`select ${className ?? ''}`} {...rest}>
      {children}
    </select>
  );
});
