import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Field } from './Field';
import { I18nProvider } from '@/shared/i18n/i18n-context';

function renderField(ui: React.ReactNode) {
  return render(<I18nProvider>{ui}</I18nProvider>);
}

describe('Field', () => {
  it('renders the label and the control', () => {
    renderField(
      <Field label="Counterparty">
        <input aria-label="cp" />
      </Field>,
    );
    expect(screen.getByText('Counterparty')).toBeInTheDocument();
    expect(screen.getByLabelText('cp')).toBeInTheDocument();
  });

  it('shows a required marker when required', () => {
    const { container } = renderField(
      <Field label="Counterparty" required>
        <input />
      </Field>,
    );
    // required marker resolves the common.required_marker locale key
    expect(container.querySelector('.req')).not.toBeNull();
  });

  it('renders hint text when provided', () => {
    renderField(
      <Field label="Amount" hint="Leave blank if not stated">
        <input />
      </Field>,
    );
    expect(screen.getByText('Leave blank if not stated')).toBeInTheDocument();
  });

  it('renders error text when provided', () => {
    renderField(
      <Field label="Amount" error="This field is required.">
        <input />
      </Field>,
    );
    expect(screen.getByText('This field is required.')).toBeInTheDocument();
  });

  it('omits hint and error nodes when not provided', () => {
    const { container } = renderField(
      <Field label="Amount">
        <input />
      </Field>,
    );
    // Only the label text node; no extra hint/error Text elements
    expect(container.querySelectorAll('p, span').length).toBeLessThanOrEqual(1);
  });
});
