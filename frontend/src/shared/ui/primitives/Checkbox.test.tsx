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

  // Regression guard for the `.checkbox` drain (#361) — asserts the replacement
  // utilities rather than the retired class name (判例#34).
  it('carries the regenerated choice-label utilities', () => {
    render(<Checkbox label="Include voided" />);
    // A function matcher picks the wrapper directly. `selector: 'label'` does not
    // work here because getByText matches on an element's own text nodes and the
    // label's text lives in a child <span>. Going through the matcher keeps this
    // inside testing-library/no-node-access rather than buying a lint override.
    const label = screen.getByText(
      (_content, element) =>
        element?.tagName === 'LABEL' && element.textContent === 'Include voided',
    );
    expect(label).toHaveClass('inline-flex', 'items-center', 'gap-2.25', 'cursor-pointer');
    expect(label).toHaveClass('text-sm', 'text-text-primary');
    // `text-sm` drags Tailwind's default --text-sm--line-height in; the old rule
    // set font-size only, so the line-height must stay inherited.
    expect(label).toHaveClass('leading-inherit');
    // `.checkbox input` → descendant utilities on the wrapper
    expect(label).toHaveClass('[&_input]:w-4', '[&_input]:h-4', '[&_input]:accent-accent');
    expect(label).not.toHaveClass('checkbox');
  });
});
