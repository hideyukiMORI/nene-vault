import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Modal } from './Modal';

describe('Modal', () => {
  it('renders as a dialog with the title and body', () => {
    render(
      <Modal title="Confirm" onClose={() => {}} closeLabel="Close">
        <p>Body</p>
      </Modal>,
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Confirm' })).toBeInTheDocument();
    expect(screen.getByText('Body')).toBeInTheDocument();
  });

  it('labels the close button with the supplied closeLabel', () => {
    render(
      <Modal title="Confirm" onClose={() => {}} closeLabel="Close">
        <p>Body</p>
      </Modal>,
    );
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument();
  });

  it('calls onClose when the close button is clicked', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <Modal title="Confirm" onClose={onClose} closeLabel="Close">
        <p>Body</p>
      </Modal>,
    );
    // The header's only button is the close control.
    await user.click(screen.getByRole('button'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('omits the header (and close button) when no title is given', () => {
    render(
      <Modal onClose={() => {}}>
        <p>Body</p>
      </Modal>,
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});
