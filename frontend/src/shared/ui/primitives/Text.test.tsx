import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Text } from './Text';

describe('Text', () => {
  it('renders as <p> by default', () => {
    render(<Text>Hello</Text>);
    expect(screen.getByText('Hello').tagName).toBe('P');
  });

  it.each(['h1', 'h2', 'span'] as const)('renders as <%s> when specified', (tag) => {
    render(<Text as={tag}>Content</Text>);
    expect(screen.getByText('Content').tagName).toBe(tag.toUpperCase());
  });

  it('renders children', () => {
    render(<Text>Hello world</Text>);
    expect(screen.getByText('Hello world')).toBeInTheDocument();
  });

  it.each(['primary', 'muted', 'danger', 'success'] as const)(
    'renders tone "%s" without throwing',
    (tone) => {
      expect(() => render(<Text tone={tone}>X</Text>)).not.toThrow();
    },
  );

  it('applies additional className', () => {
    render(<Text className="text-heading-md">Title</Text>);
    expect(screen.getByText('Title')).toHaveClass('text-heading-md');
  });
});
