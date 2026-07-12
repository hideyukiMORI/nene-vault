import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { ForbiddenPage } from './ForbiddenPage';

describe('ForbiddenPage', () => {
  it('renders a forbidden message', () => {
    renderWithProviders(
      <MemoryRouter initialEntries={['/forbidden']}>
        <ForbiddenPage />
      </MemoryRouter>,
    );
    // The page renders the problem.forbidden locale key text
    const el = screen.getByText(/permission/i);
    expect(el).toBeInTheDocument();
  });

  it('offers a way home so the page is not a dead-end (#174)', async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <MemoryRouter initialEntries={['/forbidden']}>
        <Routes>
          <Route path="/forbidden" element={<ForbiddenPage />} />
          <Route path="/" element={<div>home-landing</div>} />
        </Routes>
      </MemoryRouter>,
    );

    await user.click(screen.getByRole('button', { name: /home/i }));

    expect(screen.getByText('home-landing')).toBeInTheDocument();
  });
});
