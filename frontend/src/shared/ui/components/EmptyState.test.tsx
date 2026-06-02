import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EmptyState } from './EmptyState';

describe('EmptyState', () => {
  it('renders children inside the empty-state block', () => {
    render(<EmptyState>No documents yet</EmptyState>);
    const node = screen.getByText('No documents yet');
    expect(node).toBeInTheDocument();
    expect(node).toHaveClass('empty-state');
  });
});
