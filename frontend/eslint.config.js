import nene2 from '@hideyukimori/nene2-standards';
import eslintConfigPrettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  {
    ignores: [
      'dist',
      'storybook-static',
      'node_modules',
      'coverage',
      'src/shared/api/schema.gen.ts',
      // Config/tooling files live outside the typed project; base enables the
      // typed projectService, which errors on files it can't find in a project.
      '*.config.ts',
      'stylelint.config.js',
      'eslint.config.js',
      '.storybook/**',
      '**/*.mjs',
    ],
  },
  // base enables the typed projectService (auto-discovers tsconfig), so we only
  // supply browser globals here — no explicit parserOptions.project.
  {
    files: ['src/**/*.{ts,tsx}', 'tests/**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
    },
  },
  // Shared synthesized form (README canonical order). fsd/api/i18n/testing carry
  // the FSD boundaries, transport bans, a11y, and testing-library rules that were
  // previously hand-rolled here. styling uses the no-arg FSD-canonical entry
  // (src/shared/ui/theme/index.css).
  ...nene2.base,
  ...nene2.fsd,
  ...nene2.api,
  ...nene2.stylingWith(),
  ...nene2.i18n,
  ...nene2.testing,
  // React hygiene is not part of the fleet form; keep it as a repo-local addition.
  {
    files: ['src/**/*.{ts,tsx}'],
    plugins: { 'react-hooks': reactHooks, 'react-refresh': reactRefresh },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    },
  },

  // ── Registered exceptions (files×rule off + reason + removal condition) ──
  // These are the ONLY sanctioned deviations from the synthesized form. Each is
  // scoped to the exact files, keeps `--max-warnings 0` intact, and names the
  // condition under which the override is removed.

  // fork A — better-tailwindcss/no-unknown-classes (#281, 判例15型 / 判例21 訂正).
  // These are true unhomed utility classes, not false positives (the entryPoint
  // resolves correctly). Per the P2 per-repo shift they are classified by vault's
  // Lane D `init --scan`: classes the scan admits move to the components allowlist,
  // the rest are the true remainder that C5 drains (判例7). Until Lane D runs the
  // rule is scoped off here (kept visible in the Lane D seed report), NOT weakened
  // globally. Removal: when Lane D scan lands and wires the generated severity
  // manifest. Phase-B ledger: docs (private) todo/current.md Lane1 note.
  {
    files: [
      'src/features/document-detail/ui/DocumentHistoryTable.tsx',
      'src/features/document-detail/ui/MetadataEditModal.tsx',
      'src/features/document-detail/ui/RestoreModal.tsx',
      'src/features/document-detail/ui/VoidModal.tsx',
      'src/pages/AuditPage.tsx',
      'src/pages/ExportPage.tsx',
      'src/pages/ForbiddenPage.tsx',
      'src/pages/SettingsPage.tsx',
      'src/shared/ui/components/Callout.tsx',
      'src/shared/ui/components/Modal.stories.tsx',
      'src/shared/ui/primitives/Text.test.tsx',
    ],
    rules: { 'better-tailwindcss/no-unknown-classes': 'off' },
  },

  // format.ts — direct Intl usage (判例15, supply-coupled). The api form bans raw
  // Intl calls outside nene2-i18n's format implementation; vault's format util
  // predates that supply. Removal: when @hideyukimori/nene2-i18n's format ships
  // and vault adopts it (I18N-13). Scoped to the one util file.
  {
    files: ['src/shared/lib/format.ts'],
    rules: { 'no-restricted-syntax': 'off' },
  },

  // i18n-context.tsx — sets documentElement.lang (判例16). The rule allows the
  // lang attribute only inside the fleet i18n provider; vault's provider IS that
  // sanctioned place, but the check keys on the fleet package. Removal: fleet#118
  // (payout-raised) which teaches the rule about product-side providers.
  {
    files: ['src/shared/i18n/i18n-context.tsx'],
    rules: { 'no-restricted-syntax': 'off' },
  },

  // LanguageSwitcher.tsx — the endonym map (日本語/English) is a hardcoded string
  // by design (判例19): a language's own name is identical in every UI locale and
  // is never routed through t(). The user-facing "Language" label is already a
  // prop (#284). No removal condition — endonyms are a permanent exemption.
  {
    files: ['src/shared/ui/components/LanguageSwitcher.tsx'],
    rules: { 'no-restricted-syntax': 'off' },
  },

  eslintConfigPrettier,
);
