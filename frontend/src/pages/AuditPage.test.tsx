import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockAuditEventList } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { authStore } from '@/entities/auth';
import { AuditPage } from './AuditPage';

// Default locale in jsdom resolves to 'en' (navigator.language = 'en-US'),
// so assertions reference the English catalog. Data values (entity id, type,
// snapshot contents) are locale-independent and preferred where possible.

function renderPage() {
  return renderWithProviders(
    <MemoryRouter>
      <AuditPage />
    </MemoryRouter>,
  );
}

// The entity cell renders `{entity_type}/{entity_id}` as several text nodes in
// one <td>, so match the cell by its full textContent rather than a fragment.
const ENTITY_TEXT = `vault_document/${DOCUMENT_ID}`;
const entityCell = (_: string, el: Element | null): boolean =>
  el?.tagName === 'TD' && el.textContent === ENTITY_TEXT;

beforeEach(() => {
  authStore.setSession({
    token: 'test-jwt-token',
    userId: 1,
    email: 'admin@example.com',
    role: 'admin',
    orgId: 1,
  });
});

describe('AuditPage', () => {
  it('renders the audit log with the seeded event', async () => {
    renderPage();

    expect(await screen.findByRole('heading', { level: 1, name: 'Audit Log' })).toBeInTheDocument();
    // The event row exposes its entity type and id (locale-independent data).
    expect(await screen.findByText(entityCell)).toBeInTheDocument();
    expect(screen.getByRole('table')).toBeInTheDocument();
  });

  it('opens the detail dialog with the created values when a row is activated', async () => {
    renderPage();

    const idCell = await screen.findByText(entityCell);
    const row = idCell.closest('[role="button"]');
    expect(row).not.toBeNull();

    await userEvent.click(row as HTMLElement);

    const dialog = await screen.findByRole('dialog');
    // before_json is null → a creation event → the after snapshot is shown.
    expect(within(dialog).getByText(/Sample Inc\./)).toBeInTheDocument();
  });

  it('shows the empty state and no table when there are no events', async () => {
    server.use(
      http.get('/admin/audit-events', () =>
        HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 }),
      ),
    );

    renderPage();

    expect(await screen.findByText('No audit events')).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.queryByText(entityCell)).not.toBeInTheDocument();
  });

  it('shows an error message when the request fails', async () => {
    server.use(http.get('/admin/audit-events', () => HttpResponse.json({}, { status: 500 })));

    renderPage();

    expect(await screen.findByText('An error occurred')).toBeInTheDocument();
  });

  it('re-queries with the entity_type filter when a search is committed', async () => {
    const requestedUrls: string[] = [];
    server.use(
      http.get('/admin/audit-events', ({ request }) => {
        requestedUrls.push(request.url);
        return HttpResponse.json(mockAuditEventList);
      }),
    );

    renderPage();
    await screen.findByText(entityCell);

    // The three filter inputs are the page's text boxes, in document order:
    // entity type, entity id, action.
    const [entityTypeInput] = screen.getAllByRole('textbox');
    await userEvent.type(entityTypeInput as HTMLElement, 'vault_document');
    await userEvent.click(screen.getByRole('button', { name: 'Search' }));

    await waitFor(() => {
      expect(requestedUrls.some((u) => u.includes('entity_type=vault_document'))).toBe(true);
    });
  });
});
