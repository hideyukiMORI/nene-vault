import type { ReactNode } from 'react';

export interface TextProps {
  as?: 'p' | 'span' | 'h1' | 'h2';
  tone?: 'primary' | 'muted' | 'danger' | 'success';
  children: ReactNode;
  className?: string;
}

const TONE_CLASS: Record<NonNullable<TextProps['tone']>, string> = {
  primary: '',
  muted: 'muted',
  danger: 'danger',
  success: 'success',
};

export function Text({ as = 'p', tone = 'primary', children, className }: TextProps) {
  const Tag = as;
  return <Tag className={`${TONE_CLASS[tone]} ${className ?? ''}`.trim()}>{children}</Tag>;
}
