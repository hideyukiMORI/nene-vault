import type { ReactNode } from 'react';

export interface StackProps {
  gap?: 'sm' | 'md' | 'lg';
  children: ReactNode;
  className?: string;
}

const GAP_CLASS: Record<NonNullable<StackProps['gap']>, string> = {
  sm: 'gap-stack-sm',
  md: 'gap-stack-md',
  lg: 'gap-stack-lg',
};

export function Stack({ gap = 'md', children, className }: StackProps) {
  return <div className={`flex flex-col ${GAP_CLASS[gap]} ${className ?? ''}`}>{children}</div>;
}
