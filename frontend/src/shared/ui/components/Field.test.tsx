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
    render(
      <Field label="Counterparty" required requiredMarker="Required">
        <input />
      </Field>,
    );
    // The visible marker renders as the `.req` span carrying the supplied text.
    expect(screen.getByText('Required')).toHaveClass('req');
    expect(screen.getByText('Required')).toBeInTheDocument();
  });

  it('omits the required marker when required but no marker is supplied', () => {
    render(
      <Field label="Counterparty" required>
        <input />
      </Field>,
    );
    // required, but no marker supplied → the label renders with no marker text.
    expect(screen.getByText('Counterparty')).toBeInTheDocument();
    expect(screen.queryByText('Required')).not.toBeInTheDocument();
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
    render(
      <Field label="Amount">
        <input />
      </Field>,
    );
    // Neither the hint nor the error text (used in the tests above) is rendered.
    expect(screen.getByText('Amount')).toBeInTheDocument();
    expect(screen.queryByText('Leave blank if not stated')).not.toBeInTheDocument();
    expect(screen.queryByText('This field is required.')).not.toBeInTheDocument();
  });
});
