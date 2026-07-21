import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Button } from './Button';

describe('Button', () => {
  it('renders children', () => {
    render(<Button>Save</Button>);
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  });

  it('calls onClick when clicked', async () => {
    const handler = vi.fn();
    render(<Button onClick={handler}>Click me</Button>);
    await userEvent.click(screen.getByRole('button'));
    expect(handler).toHaveBeenCalledOnce();
  });

  it('is disabled when disabled prop is set', () => {
    render(<Button disabled>Save</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('does not fire onClick when disabled', async () => {
    const handler = vi.fn();
    render(
      <Button disabled onClick={handler}>
        Save
      </Button>,
    );
    await userEvent.click(screen.getByRole('button'));
    expect(handler).not.toHaveBeenCalled();
  });

  it('renders with type="submit" when specified', () => {
    render(<Button type="submit">Submit</Button>);
    expect(screen.getByRole('button')).toHaveAttribute('type', 'submit');
  });

  it.each(['primary', 'secondary', 'danger', 'ghost'] as const)(
    'renders variant "%s" without throwing',
    (variant) => {
      expect(() => render(<Button variant={variant}>X</Button>)).not.toThrow();
    },
  );

  // Regression guard for the VARIANT_UTILS map form (判例#35): the variant
  // resolves to colour utilities (not a `.btn-*` component class), so assert the
  // utilities are present — a className-only check on the retired class could not
  // guard the paint.
  it.each([
    ['primary', 'bg-accent'],
    ['secondary', 'bg-surface-raised'],
    ['danger', 'bg-danger'],
    ['ghost', 'bg-transparent'],
  ] as const)('maps variant "%s" to its colour utility', (variant, util) => {
    render(<Button variant={variant}>X</Button>);
    expect(screen.getByRole('button')).toHaveClass(util);
  });

  it.each([
    ['md', 'py-2'],
    ['sm', 'py-1.25'],
  ] as const)('maps size "%s" to its padding utility', (size, util) => {
    render(<Button size={size}>X</Button>);
    expect(screen.getByRole('button')).toHaveClass(util);
  });
});
