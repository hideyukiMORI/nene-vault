import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { mockDocument, mockVoidedDocument } from '@tests/msw/fixtures';
import { DocumentTable } from './DocumentTable';

describe('DocumentTable', () => {
  it('shows empty state message when documents is empty', () => {
    renderWithProviders(<DocumentTable documents={[]} onSelectDocument={vi.fn()} />);
    // Japanese locale: "データがありません" or the document.list.empty key
    expect(screen.getByText(/No documents/i)).toBeInTheDocument();
  });

  /**
   * The empty state is centred and announced. Both used to be asserted against vault's own
   * `EmptyState` component, which #390 replaced with the kit's — so the assertion moved here,
   * to the rendered result, rather than being deleted with the component. A guarantee the
   * product makes does not stop being the product's when the implementation moves upstream.
   */
  it('centres the empty state and announces it', () => {
    renderWithProviders(<DocumentTable documents={[]} onSelectDocument={vi.fn()} />);
    const node = screen.getByText(/No documents/i);
    expect(node).toHaveClass('text-center');
    expect(node).toHaveAttribute('role', 'status');
  });

  it('renders a row for each document', () => {
    renderWithProviders(
      <DocumentTable documents={[mockDocument, mockVoidedDocument]} onSelectDocument={vi.fn()} />,
    );
    expect(screen.getAllByRole('row')).toHaveLength(3); // header + 2 data rows
  });

  it('displays counterparty_name', () => {
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={vi.fn()} />);
    expect(screen.getByText(mockDocument.counterparty_name)).toBeInTheDocument();
  });

  it('displays amount in JPY format', () => {
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={vi.fn()} />);
    // ¥110,000 or similar
    expect(screen.getByText(/110/)).toBeInTheDocument();
  });

  it('shows "—" for null amount', () => {
    const doc = { ...mockDocument, amount_cents: null };
    renderWithProviders(<DocumentTable documents={[doc]} onSelectDocument={vi.fn()} />);
    expect(screen.getAllByText('—').length).toBeGreaterThan(0);
  });

  it('marks date-uncertain with an asterisk', () => {
    const doc = { ...mockDocument, date_uncertain: true, transaction_date: '2026-03-31' };
    renderWithProviders(<DocumentTable documents={[doc]} onSelectDocument={vi.fn()} />);
    expect(screen.getByText('2026-03-31')).toBeInTheDocument();
    // the uncertain marker renders as a separate faint asterisk node
    expect(screen.getByText('*')).toBeInTheDocument();
  });

  it('calls onSelectDocument with the document id when detail link is clicked', async () => {
    const handler = vi.fn();
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={handler} />);
    await userEvent.click(screen.getByRole('button', { name: /Detail/i }));
    expect(handler).toHaveBeenCalledWith(mockDocument.id);
  });

  it('shows Voided status badge for voided document', () => {
    renderWithProviders(
      <DocumentTable documents={[mockVoidedDocument]} onSelectDocument={vi.fn()} />,
    );
    expect(screen.getByText('Voided')).toBeInTheDocument();
  });

  it('shows Active status badge for active document', () => {
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={vi.fn()} />);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  // Regression guard: the status badge is the kit's `Badge` (0.17.0, W1b) carrying this
  // product's dot through `className`. Asserts the kit's tone slot class and the dot
  // utilities, not a retired class name (判例#34).
  it('renders the status as the kit Badge with the product dot', () => {
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={vi.fn()} />);
    const badge = screen.getByText('Active');
    expect(badge).toHaveClass('inline-flex', 'items-center', 'border');
    expect(badge).toHaveClass('bg-x-slot-badge-success-bg', 'text-x-slot-badge-success-fg');
    expect(badge).toHaveClass('text-2xs', 'font-semibold', 'leading-badge');
    // `.badge::before` — the dot, now `BADGE_CHROME`.
    expect(badge).toHaveClass(
      'gap-1.5',
      'before:w-1.5',
      'before:h-1.5',
      'before:rounded-full',
      'before:bg-current',
    );
    expect(badge).not.toHaveClass('badge');
    expect(badge).not.toHaveAttribute('data-tone');
  });
});
