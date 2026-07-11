import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { SettingsPage } from './SettingsPage';

describe('SettingsPage retention warning', () => {
  it('shows the under-10-years warning live while typing, before any save (#175)', async () => {
    renderWithProviders(
      <MemoryRouter>
        <SettingsPage />
      </MemoryRouter>,
    );

    // Settings load with retention_years = 10 → no warning yet.
    const input = await screen.findByRole('spinbutton');
    await waitFor(() => {
      expect(input).toHaveValue(10);
    });
    expect(input).not.toHaveClass('input-warn');

    // Typing a value below 10 must flag the field immediately — no save required.
    await userEvent.clear(input);
    await userEvent.type(input, '8');

    await waitFor(() => {
      expect(input).toHaveClass('input-warn');
    });
  });
});
