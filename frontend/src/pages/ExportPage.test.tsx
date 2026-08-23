import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { server } from '@tests/msw/server';
import { ExportPage } from './ExportPage';

/**
 * The export screen had no test until #390 wave 3, which is also when its format radios
 * stopped being hand-written `<label><input type="radio">` markup and became the kit's
 * `Radio`. The swap is only safe if the group still behaves as a group — one name, one
 * selection, and the choice reaching the request — so that is what these assert.
 */
function renderPage() {
  return renderWithProviders(
    <MemoryRouter>
      <ExportPage />
    </MemoryRouter>,
  );
}

describe('ExportPage', () => {
  it('offers the two formats as one radio group, with zip selected', async () => {
    renderPage();

    const zip = await screen.findByRole('radio', { name: /zip/i });
    const csv = screen.getByRole('radio', { name: /csv/i });

    // One group: radios only mean anything when they share a name.
    expect(zip).toHaveAttribute('name', csv.getAttribute('name'));
    expect(zip).toBeChecked();
    expect(csv).not.toBeChecked();
  });

  it('moves the selection when the other format is chosen', async () => {
    renderPage();

    const csv = await screen.findByRole('radio', { name: /csv/i });
    await userEvent.click(csv);

    expect(csv).toBeChecked();
    expect(screen.getByRole('radio', { name: /zip/i })).not.toBeChecked();
  });

  it('sends the chosen format and the voided flag to the API', async () => {
    const body = vi.fn();
    server.use(
      http.post('/admin/vault/export', async ({ request }) => {
        body(await request.json());
        return HttpResponse.text('id,amount\n', {
          headers: { 'Content-Type': 'text/csv' },
        });
      }),
    );

    renderPage();

    await userEvent.click(await screen.findByRole('radio', { name: /csv/i }));
    await userEvent.click(screen.getByRole('checkbox'));
    await userEvent.click(screen.getByRole('button', { name: /export|エクスポート/i }));

    await waitFor(() => {
      expect(body).toHaveBeenCalledOnce();
    });
    expect(body.mock.calls[0]?.[0]).toMatchObject({ format: 'csv', include_voided: true });
  });

  it('reports a failed export instead of claiming success', async () => {
    server.use(http.post('/admin/vault/export', () => new HttpResponse(null, { status: 500 })));

    renderPage();
    await userEvent.click(await screen.findByRole('button', { name: /export|エクスポート/i }));

    await waitFor(() => {
      expect(screen.queryByText(/downloaded|ダウンロード/i)).not.toBeInTheDocument();
    });
  });
});
