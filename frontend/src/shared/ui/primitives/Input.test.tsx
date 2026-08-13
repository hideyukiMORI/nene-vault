import { createRef } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Input } from './Input';

describe('Input', () => {
  it('renders an input element', () => {
    render(<Input />);
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('forwards its ref to the underlying input (required by React Hook Form)', () => {
    const ref = createRef<HTMLInputElement>();
    render(<Input ref={ref} />);
    expect(ref.current).toBeInstanceOf(HTMLInputElement);
  });

  it('forwards type prop', () => {
    render(<Input type="email" />);
    expect(screen.getByRole('textbox')).toHaveAttribute('type', 'email');
  });

  it('forwards placeholder', () => {
    render(<Input placeholder="Enter email" />);
    expect(screen.getByPlaceholderText('Enter email')).toBeInTheDocument();
  });

  it('calls onChange when user types', async () => {
    const handler = vi.fn();
    render(<Input onChange={handler} />);
    await userEvent.type(screen.getByRole('textbox'), 'abc');
    expect(handler).toHaveBeenCalled();
  });

  it('respects disabled prop', () => {
    render(<Input disabled />);
    expect(screen.getByRole('textbox')).toBeDisabled();
  });

  it('displays current value', () => {
    render(<Input readOnly value="test value" />);
    expect(screen.getByDisplayValue('test value')).toBeInTheDocument();
  });

  // Regression guard for the `.input` drain (#361). Per 判例#34 these assert the
  // *replacement utilities*, not a class name: a class name survives its CSS
  // being deleted, so `toHaveClass('input')` would have passed straight through
  // the silent-regression case that #337/#338 were about.
  it('carries the regenerated field utilities', () => {
    render(<Input />);
    const el = screen.getByRole('textbox');
    // shared base — box, colour and focus ring
    expect(el).toHaveClass('border-x-line-mid', 'bg-surface-raised', 'rounded-sm');
    expect(el).toHaveClass('py-2', 'px-2.75', 'text-body', 'text-x-ink-deep');
    expect(el).toHaveClass('focus:border-accent', 'focus:ring-3', 'focus:ring-accent-soft');
    // input-only additions
    expect(el).toHaveClass('placeholder:text-text-faint');
    // touch block that replaced @media (max-width: 767px)
    expect(el).toHaveClass('max-md:py-2.75', 'max-md:px-3', 'max-md:text-touch');
    // the retired component class must be gone
    expect(el).not.toHaveClass('input');
  });

  it('keeps the aria-invalid styling hook from #345', () => {
    render(<Input aria-invalid />);
    expect(screen.getByRole('textbox')).toHaveClass(
      'aria-invalid:border-warn',
      'aria-invalid:ring-3',
      'aria-invalid:ring-warn-soft',
    );
  });
});
