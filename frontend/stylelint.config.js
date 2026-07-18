import { stylelintConfigFor } from '@hideyukimori/nene2-standards/stylelint';

// #65 arm: the effective allowlist/manifest is baked from the central
// registries (@hideyukimori/nene2-standards) keyed by this repo name — the
// product never hand-maintains the list (G-7). See docs/todo/current.md #238.
export default stylelintConfigFor('nene-vault');
