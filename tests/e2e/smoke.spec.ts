import { expect, test, type Route } from '@playwright/test';

// Minimal @smoke — fleet T2 (Issue #279; payout #243 型).
//
// Critical path: boot the SPA → sign in → land on the authenticated shell
// (HomePage + rail nav). The API is stubbed (page.route) so the run is hermetic
// — no backend, DB, or Docker. This asserts the *frontend wiring* of the login
// flow (form → session store → in-place AuthGate → authed shell render), not the
// real backend auth, which is covered by backend + unit tests.
//
// Contract mirrored from src/entities/auth/mutations.ts + docs/openapi:
//   POST /admin/auth/login -> { token, user_id, email, role, org_id }
// Vault derives the whole session from the login response — there is no separate
// /me call. The apiBaseUrl defaults to '' so requests are same-origin /admin/*.

const json = (route: Route, body: unknown): Promise<void> =>
  route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });

const emptyList = { items: [], total: 0 };

test('@smoke sign in and reach the authenticated shell', async ({ page }) => {
  await page.route('**/admin/**', async (route) => {
    const url = route.request().url();
    const method = route.request().method();

    if (url.includes('/admin/auth/login') && method === 'POST') {
      await json(route, {
        token: 'smoke-token',
        user_id: 'usr_smoke',
        email: 'smoke@example.com',
        role: 'admin',
        org_id: 'org_smoke',
      });
      return;
    }
    // Any other admin call (list/detail the shell may issue) → empty payload.
    await json(route, emptyList);
  });

  // Boot → login page.
  await page.goto('/login');
  await expect(page.locator('input[type="email"]')).toBeVisible();

  // Sign in.
  await page.locator('input[type="email"]').fill('smoke@example.com');
  await page.locator('input[type="password"]').fill('correct horse battery staple');
  await page.locator('button[type="submit"]').click();

  // Land on the authenticated shell: LoginPage redirects to '/' on success and
  // the AppShell rail nav renders (proves the authed shell, not just a token).
  await expect(page).toHaveURL(/\/$/);
  const railNav = page.locator('nav.rail-nav');
  await expect(railNav).toBeVisible();
  // The capability-gated rail rendered links; an admin sees several. Nav items
  // are <button> that route via onClick (not <a href>), so prove the wiring by
  // navigating: the second rail link is Documents (order: Home, Documents, …)
  // and admin has ViewDocuments.
  const railLinks = railNav.locator('button.rail-link');
  expect(await railLinks.count()).toBeGreaterThanOrEqual(2);
  await railLinks.nth(1).click();
  await expect(page).toHaveURL(/\/documents$/);
});
