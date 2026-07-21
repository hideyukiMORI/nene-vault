import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EmptyState } from './EmptyState';

describe('EmptyState', () => {
  it('renders children in the centered empty-state layout', () => {
    // The `.empty-state` component class was regenerated into Tailwind utilities
    // (C5 W3 波(a)) — assert the centering layout survives the migration.
    render(<EmptyState>No documents yet</EmptyState>);
    const node = screen.getByText('No documents yet');
    expect(node).toBeInTheDocument();
    expect(node).toHaveClass('flex', 'items-center', 'justify-center');
  });
});
