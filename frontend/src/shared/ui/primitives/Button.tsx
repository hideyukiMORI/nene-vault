import type { ButtonHTMLAttributes, ReactNode } from 'react';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger';
  children: ReactNode;
}

const VARIANT_CLASS: Record<NonNullable<ButtonProps['variant']>, string> = {
  primary: 'bg-accent text-accent-text border-transparent',
  secondary: 'bg-surface-raised text-text-primary border-border',
  danger: 'bg-danger text-accent-text border-transparent',
};

export function Button({ variant = 'primary', className, children, ...rest }: ButtonProps) {
  const variantClass = VARIANT_CLASS[variant];
  return (
    <button
      className={`rounded-md border px-inline-md py-stack-sm font-sans text-body disabled:opacity-50 ${variantClass} ${className ?? ''}`}
      {...rest}
    >
      {children}
    </button>
  );
}
