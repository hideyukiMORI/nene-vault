import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Field } from './Field';

describe('Field', () => {
  it('renders the label and the control', () => {
    render(
      <Field label="Counterparty">
        <input aria-label="cp" />
      </Field>,
    );
    expect(screen.getByText('Counterparty')).toBeInTheDocument();
    expect(screen.getByLabelText('cp')).toBeInTheDocument();
  });

  it('shows the required marker when required and a marker is supplied', () => {
    const { container } = render(
      <Field label="Counterparty" required requiredMarker="Required">
        <input />
      </Field>,
    );
    expect(container.querySelector('.req')).not.toBeNull();
    expect(screen.getByText('Required')).toBeInTheDocument();
  });

  it('omits the required marker when required but no marker is supplied', () => {
    const { container } = render(
      <Field label="Counterparty" required>
        <input />
      </Field>,
    );
    expect(container.querySelector('.req')).toBeNull();
  });

  it('renders hint text when provided', () => {
    render(
      <Field label="Amount" hint="Leave blank if not stated">
        <input />
      </Field>,
    );
    expect(screen.getByText('Leave blank if not stated')).toBeInTheDocument();
  });

  it('renders error text when provided', () => {
    render(
      <Field label="Amount" error="This field is required.">
        <input />
      </Field>,
    );
    expect(screen.getByText('This field is required.')).toBeInTheDocument();
  });

  it('omits hint and error nodes when not provided', () => {
    const { container } = render(
      <Field label="Amount">
        <input />
      </Field>,
    );
    // Only the label text node; no extra hint/error Text elements
    expect(container.querySelectorAll('p, span').length).toBeLessThanOrEqual(1);
  });
});
