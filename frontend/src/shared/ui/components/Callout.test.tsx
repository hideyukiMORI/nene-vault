import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Callout } from './Callout';

describe('Callout', () => {
  it('renders children', () => {
    render(<Callout tone="danger">Something failed</Callout>);
    expect(screen.getByText('Something failed')).toBeInTheDocument();
  });

  it.each(['danger', 'warn'] as const)('applies the %s tone class', (tone) => {
    render(<Callout tone={tone}>Message</Callout>);
    expect(screen.getByText('Message')).toHaveClass('callout', `callout-${tone}`);
  });
});
