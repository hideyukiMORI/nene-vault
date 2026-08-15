import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockAuditEventList } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { authStore } from '@/shared/api/auth-session';
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

    // The row is a role=button whose accessible name is built from its cell
    // text, so it includes the entity type/id (ignoring incidental whitespace).
    const row = await screen.findByRole('button', {
      name: (name) => name.replace(/\s/g, '').includes(ENTITY_TEXT),
    });

    await userEvent.click(row);

    const dialog = await screen.findByRole('dialog');
    // before_json is null → a creation event → the after snapshot is shown.
    expect(within(dialog).getByText(/Sample Inc\./)).toBeInTheDocument();
  });

  // Regression guard for the `.diff-arrow` drain (#371) — asserts the
  // replacement utilities rather than the retired class name (判例#34). The
  // seeded fixture is a creation event, so the arrow never rendered in any test
  // before this one: deleting its CSS would have gone unnoticed.
  it('carries the regenerated diff-arrow utilities on a change event', async () => {
    server.use(
      http.get('/admin/audit-events', () =>
        HttpResponse.json({
          ...mockAuditEventList,
          items: [
            {
              ...mockAuditEventList.items[0],
              action: 'document.metadata_updated',
              before_json: { counterparty_name: 'Old Inc.' },
              after_json: { counterparty_name: 'New Inc.' },
            },
          ],
        }),
      ),
    );
    renderPage();

    const row = await screen.findByRole('button', {
      name: (name) => name.replace(/\s/g, '').includes(ENTITY_TEXT),
    });
    await userEvent.click(row);
    const dialog = await screen.findByRole('dialog');
    // `formatAuditValue` quotes strings, so the before box reads `"Old Inc."`.
    expect(within(dialog).getByText('"Old Inc."')).toBeInTheDocument();

    // A function matcher reaches the arrow the same way the entity cell above is
    // matched: on structure, not on the classes under test (which would make the
    // assertion circular). It also keeps this inside
    // testing-library/no-node-access rather than buying a lint override.
    const arrow = within(dialog).getByText(
      (_content, el) =>
        el?.tagName === 'DIV' && el.children.length === 1 && el.children[0]?.tagName === 'svg',
    );
    expect(arrow).toHaveClass('flex', 'items-center', 'justify-center', 'text-text-faint');
    // The old `@media (max-width: 767px)` half of the rule.
    expect(arrow).toHaveClass('max-md:justify-start', 'max-md:py-px', 'max-md:pl-0.75');
    expect(arrow).not.toHaveClass('diff-arrow');

    // Scoped with `within(arrow)` rather than walking to `parentElement`, which
    // testing-library/no-node-access forbids.
    const icon = within(arrow).getByText((_content, el) => el?.tagName === 'svg');
    expect(icon).toHaveClass('w-3.75', 'h-3.75', 'stroke-current', 'max-md:rotate-90');

    // `.diff-pair` / `.diff-field` (#371). Reached through the arrow rather than
    // by class, again to keep the assertion non-circular.
    const pair = within(dialog).getByText(
      (_content, el) =>
        el?.tagName === 'DIV' && el.children.length === 3 && el.children[1] === arrow,
    );
    expect(pair).toHaveClass('grid', 'items-stretch', 'grid-cols-1', 'gap-1.5', 'md:gap-2');
    // A change event keeps the three-column template from `md:` up.
    expect(pair).toHaveClass('md:diff-cols');
    expect(pair).not.toHaveClass('diff-pair');

    const field = within(dialog).getByText(
      (_content, el) =>
        el?.tagName === 'DIV' && el.children.length === 2 && el.children[1] === pair,
    );
    expect(field).toHaveClass('[&+&]:mt-3.25');
    expect(field).not.toHaveClass('diff-field');
  });

  // The creation-event half of the same drain: `.diff-single .diff-pair` (one
  // column at every width) became the absence of `md:diff-cols` (#371).
  it('omits the three-column template on a creation event', async () => {
    renderPage();

    const row = await screen.findByRole('button', {
      name: (name) => name.replace(/\s/g, '').includes(ENTITY_TEXT),
    });
    await userEvent.click(row);
    const dialog = await screen.findByRole('dialog');

    const afterBox = within(dialog).getByText(/Sample Inc\./);
    const pair = within(dialog).getByText(
      (_content, el) =>
        el?.tagName === 'DIV' && el.children.length === 1 && el.children[0] === afterBox,
    );
    expect(pair).toHaveClass('grid', 'grid-cols-1', 'gap-1.5', 'md:gap-2');
    expect(pair).not.toHaveClass('md:diff-cols');
    expect(pair).not.toHaveClass('diff-single');
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
