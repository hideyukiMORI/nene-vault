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

  // Regression guard for the `.badge` drain (#369) — asserts the replacement
  // utilities rather than the retired class name (判例#34). The two tests above
  // only assert the label text, so deleting the CSS would have gone unnoticed.
  it('carries the regenerated badge utilities', () => {
    renderWithProviders(<DocumentTable documents={[mockDocument]} onSelectDocument={vi.fn()} />);
    const badge = screen.getByText('Active');
    expect(badge).toHaveClass('inline-flex', 'items-center', 'gap-1.5', 'rounded-full');
    expect(badge).toHaveClass('px-2.5', 'py-0.75', 'text-2xs', 'font-semibold');
    // The old rule set `line-height: 1.4` explicitly; `text-2xs` has no partner
    // `--text-2xs--line-height` to supply one, so the ratio needs its own token
    // (判例40).
    expect(badge).toHaveClass('leading-badge');
    // `.badge::before` — the dot.
    expect(badge).toHaveClass(
      'before:w-1.5',
      'before:h-1.5',
      'before:rounded-full',
      'before:bg-current',
    );
    // Tone stays runtime-driven at the use site.
    expect(badge).toHaveAttribute('data-tone', 'success');
    expect(badge).not.toHaveClass('badge');
  });
});
