# Frontend Standards

**Status: Phase 2 — not yet implemented.**

This document will define the React + TypeScript admin UI standards for NeNe Vault when Phase 2 begins. Until then, this file is a placeholder and no frontend code should be added.

## Framework

- React + TypeScript (strict mode)
- Vite for local development and production builds
- npm as the official package manager; Active Node.js LTS
- ESLint + Prettier for linting and formatting

## Locale

- UI locale: **ja (primary) + en (secondary) only** (ADR 0005)
- Default language: `ja` (fallback when `Accept-Language` does not match `en`)
- Language selector in admin UI: toggle ja / en
- Statutory field labels (取引年月日, 取引金額, 取引先) appear in Japanese in both locales

## Planned Phase 2 content

- Component structure and folder layout
- API client wrapper (maps snake_case JSON from backend)
- State management approach
- Admin route structure
- PDF inline viewer for received documents
- File upload wizard with metadata form
- Search form (date range, amount range, counterparty)
- Audit history view

## Commands (Phase 2+)

```bash
npm install --prefix frontend
npm run dev --prefix frontend
npm run build --prefix frontend
npm run check --prefix frontend
```
