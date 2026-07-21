import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { Pagination } from './Pagination';

const base = {
  total: 50,
  canPrev: false,
  canNext: true,
  onPrev: vi.fn(),
  onNext: vi.fn(),
  showingLabel: 'Showing 1–20 of 50',
  previousLabel: 'Previous',
  nextLabel: 'Next',
};

describe('Pagination', () => {
  it('renders nothing when total is 0', () => {
    const { container } = renderWithProviders(<Pagination {...base} total={0} canNext={false} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders Previous/Next buttons when total > 0', () => {
    renderWithProviders(<Pagination {...base} />);
    expect(screen.getByRole('button', { name: 'Previous' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Next' })).toBeInTheDocument();
  });

  it('disables Previous when canPrev is false', () => {
    renderWithProviders(<Pagination {...base} canPrev={false} />);
    expect(screen.getByRole('button', { name: 'Previous' })).toBeDisabled();
  });

  it('enables Previous when canPrev is true', () => {
    renderWithProviders(<Pagination {...base} canPrev={true} />);
    expect(screen.getByRole('button', { name: 'Previous' })).not.toBeDisabled();
  });

  it('disables Next when canNext is false', () => {
    renderWithProviders(<Pagination {...base} canNext={false} />);
    expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled();
  });

  it('calls onNext when Next is clicked', async () => {
    const onNext = vi.fn();
    renderWithProviders(<Pagination {...base} onNext={onNext} />);
    await userEvent.click(screen.getByRole('button', { name: 'Next' }));
    expect(onNext).toHaveBeenCalledOnce();
  });

  it('calls onPrev when Previous is clicked', async () => {
    const onPrev = vi.fn();
    renderWithProviders(<Pagination {...base} canPrev={true} onPrev={onPrev} />);
    await userEvent.click(screen.getByRole('button', { name: 'Previous' }));
    expect(onPrev).toHaveBeenCalledOnce();
  });

  it('renders the supplied showing-range label', () => {
    // The consumer formats the range; the component just renders the string.
    renderWithProviders(<Pagination {...base} showingLabel="Showing 41–45 of 45" />);
    expect(screen.getByText(/45/)).toBeInTheDocument();
  });
});
