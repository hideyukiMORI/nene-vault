import type { InputHTMLAttributes } from 'react';

export type InputProps = InputHTMLAttributes<HTMLInputElement>;

export function Input({ className, ...rest }: InputProps) {
  return (
    <input
      className={`rounded-md border border-border bg-surface-raised px-inline-md py-stack-sm font-sans text-body text-text-primary ${className ?? ''}`}
      {...rest}
    />
  );
}
