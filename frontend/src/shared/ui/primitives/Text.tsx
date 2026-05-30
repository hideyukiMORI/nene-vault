import type { ReactNode } from 'react';

export interface TextProps {
  as?: 'p' | 'span' | 'h1' | 'h2';
  tone?: 'primary' | 'muted' | 'danger';
  children: ReactNode;
  className?: string;
}

const TONE_CLASS: Record<NonNullable<TextProps['tone']>, string> = {
  primary: 'text-text-primary',
  muted: 'text-text-muted',
  danger: 'text-danger',
};

export function Text({ as = 'p', tone = 'primary', children, className }: TextProps) {
  const Tag = as;
  return (
    <Tag className={`font-sans text-body ${TONE_CLASS[tone]} ${className ?? ''}`}>{children}</Tag>
  );
}
