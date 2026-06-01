import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Modal } from './Modal';
import { I18nProvider } from '@/shared/i18n/i18n-context';

function renderModal(ui: React.ReactNode) {
  return render(<I18nProvider>{ui}</I18nProvider>);
}

describe('Modal', () => {
  it('renders as a dialog with the title and body', () => {
    renderModal(
      <Modal title="Confirm" onClose={() => {}}>
        <p>Body</p>
      </Modal>,
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Confirm' })).toBeInTheDocument();
    expect(screen.getByText('Body')).toBeInTheDocument();
  });

  it('calls onClose when the close button is clicked', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    renderModal(
      <Modal title="Confirm" onClose={onClose}>
        <p>Body</p>
      </Modal>,
    );
    // The header's only button is the close control.
    await user.click(screen.getByRole('button'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('omits the header (and close button) when no title is given', () => {
    renderModal(
      <Modal onClose={() => {}}>
        <p>Body</p>
      </Modal>,
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});
