import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Callout } from './Callout';

describe('Callout', () => {
  it('renders children', () => {
    render(<Callout tone="danger">Something failed</Callout>);
    expect(screen.getByText('Something failed')).toBeInTheDocument();
  });

  // Regression (#337): the tone colours lived in `.callout-${tone}` component
  // classes that drain #4 (#316) removed while this component still referenced
  // them via a template literal — asserting the tone *colour utilities* (not the
  // class name) now guards the paint, which the old `toHaveClass('callout-…')`
  // assertion could not (className present, CSS absent = silent regression).
  it('applies the danger tone colours', () => {
    render(<Callout tone="danger">Message</Callout>);
    expect(screen.getByText('Message')).toHaveClass(
      'bg-danger-soft',
      'border-danger',
      'text-x-danger-hover',
    );
  });

  it('applies the warn tone colours', () => {
    render(<Callout tone="warn">Message</Callout>);
    expect(screen.getByText('Message')).toHaveClass('bg-warn-soft', 'border-warn', 'text-on-warn');
  });
});
