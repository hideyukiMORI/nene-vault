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
    // The under-10 warning now hangs off `aria-invalid` (C5 W3 波W3, FC-1.8) —
    // the styling regenerated from `.input-warn` follows this attribute, so
    // assert the attribute (a11y + paint source) rather than the retired class.
    expect(input).not.toHaveAttribute('aria-invalid');

    // Typing a value below 10 must flag the field immediately — no save required.
    await userEvent.clear(input);
    await userEvent.type(input, '8');

    await waitFor(() => {
      // 🔴 Two assertions, and they have to fail for different reasons.
      //
      //   1. this page marks the field   — `data-warn` is on the input
      //   2. the kit paints that mark    — the classes keyed off it are on the input
      //
      // Asserting only (1) is the shape that already got through here once: the attribute
      // survived the migration, the paint did not, and the test stayed green because it was
      // checking a stand-in that lived in the same component as the thing it stood for. The
      // moment the implementation moved upstream, the stand-in stayed behind.
      //
      // Asserting only (2) would pass on a field that is never marked at all.
      //
      // ⚠️ Neither reaches computed style — jsdom has no compiled CSS. What they do reach is
      // the two edits that actually removed the signal last time: dropping `data-warn` here,
      // and the kit dropping `VALIDITY_CLASS` upstream. Verified by making each of those
      // edits and watching this go red (2026-08-24).
      expect(input).toHaveAttribute('data-warn');
    });

    // Outside the `waitFor`: the class list is fixed at render, so there is nothing to wait
    // for here — and one assertion per callback is the lint rule.
    expect(input.className).toContain('data-[warn]:border-x-slot-control-warn-border');
    expect(input.className).toContain('data-[warn]:bg-x-slot-control-warn-bg');
  });

  /**
   * Closing the last of #385 in the one place that is not an error.
   *
   * 🔴 The field used to set `aria-invalid` for the under-ten-years notice. Eight years is
   * over the `min` of seven, so the value is valid — `aria-invalid` said it was not, and the
   * reason was never linked. Both halves were wrong, and neither is visible in a render.
   */
  it('links the retention notice to the field without calling the value invalid', async () => {
    renderWithProviders(
      <MemoryRouter>
        <SettingsPage />
      </MemoryRouter>,
    );

    const input = await screen.findByRole('spinbutton');
    await waitFor(() => {
      expect(input).toHaveValue(10);
    });

    await userEvent.clear(input);
    await userEvent.type(input, '8');

    // Scoped to the alert itself: the hint under the field says "10 years or more" too, and
    // the point of this test is which of the two the field points at.
    await screen.findByRole('status');

    // The value passes validation, so it must not be announced as invalid.
    expect(input).not.toHaveAttribute('aria-invalid', 'true');

    // …and the reason it is flagged has to be reachable from the field. Compared by id
    // rather than by walking the DOM: what matters is that the field points at the element
    // holding the notice, which is exactly what a screen reader follows.
    //
    // The id sits on the alert itself since 0.12.0 — `InlineAlert` takes an `id` now, so the
    // wrapper this used to read through (`.parentElement`) is gone.
    const describedBy = input.getAttribute('aria-describedby');
    expect(describedBy).not.toBeNull();
    expect(screen.getByRole('status').id).toBe(describedBy);
  });
});
