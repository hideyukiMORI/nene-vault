import { stylelintConfigFor } from '@hideyukimori/nene2-standards/stylelint';

// The config itself comes from the central package; the components allowlist it
// enforces is THIS repo's `frontend/registries.jsonc` (per-repo since the 2026-07-21
// ruling — `nene2-check init --scan` seeds it, drains edit it by hand; the C5 drain
// took it 153 → 16). `@hideyukimori/nene2-standards` ships only the schema, not the
// list. History: #65 (central arm) → #238 → #356 (this comment). The living TODO is
// private (`nene-origin/internal-docs/vault/todo/current.md`), not `docs/todo/`.
export default stylelintConfigFor('nene-vault');
