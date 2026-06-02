import { createRef } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Checkbox } from './Checkbox';

describe('Checkbox', () => {
  it('renders a checkbox labelled by its text', () => {
    render(<Checkbox label="Include voided" />);
    expect(screen.getByRole('checkbox', { name: 'Include voided' })).toBeInTheDocument();
  });

  it('fires onChange when toggled', async () => {
    const handler = vi.fn();
    render(<Checkbox label="Include voided" onChange={handler} />);
    await userEvent.click(screen.getByRole('checkbox', { name: 'Include voided' }));
    expect(handler).toHaveBeenCalledOnce();
  });

  it('reflects the controlled checked state', () => {
    render(<Checkbox label="Include voided" checked readOnly />);
    expect(screen.getByRole('checkbox', { name: 'Include voided' })).toBeChecked();
  });

  it('forwards its ref to the input element', () => {
    const ref = createRef<HTMLInputElement>();
    render(<Checkbox label="Include voided" ref={ref} />);
    expect(ref.current).toBeInstanceOf(HTMLInputElement);
  });
});
