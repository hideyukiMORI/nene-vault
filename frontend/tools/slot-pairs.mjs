// 対になっているスロットの「片側だけ上書き」を検出する（#435）。
//
// 🔴 なぜ要るか: `--text-x-slot-button-size` と `--text-x-slot-button-sm-size` は
// どちらも既定が `inherit` で、両方が既定なら `sm` と `md` は同じ大きさになり反転しない。
// 反転するのは **`md` だけを上書きした製品** ——このリポがまさにそれで、2026-08-24 に
// `sm` を置かなければ `sm`(14px 継承) > `md`(13px) になるところだった（#402）。
//
// 対の一覧はキットが `themes/slot-pairs.json` で配布する（0.15.0〜）。**手で持たない。**
// キットが対を増やせば、この検査の射程も自動で増える。
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const PAIRS = require('@hideyukimori/nene2-ui/themes/slot-pairs.json');
const THEME = 'src/shared/ui/theme/themes/default.css';

const css = readFileSync(THEME, 'utf8');
/** そのカスタムプロパティを、このテーマが宣言しているか。 */
const declares = (name) => new RegExp(`^\\s*${name.replace(/[-]/g, '\\-')}\\s*:`, 'm').test(css);

const offenders = [];
for (const pair of PAIRS.pairs) {
  if (!pair.overrideTogether) continue;
  const base = declares(pair.base);
  const sm = declares(pair.sm);
  if (base !== sm) {
    offenders.push({ pair, missing: base ? pair.sm : pair.base, has: base ? pair.base : pair.sm });
  }
}

const together = PAIRS.pairs.filter((p) => p.overrideTogether).length;
if (offenders.length > 0) {
  console.error(`slot-pairs: ${offenders.length} 件の片側上書き\n`);
  for (const o of offenders) {
    console.error(`  🔴 ${o.has} を上書きしているのに ${o.missing} を上書きしていない`);
    console.error(
      `     既定は両方 ${JSON.stringify(o.pair.defaults)} なので、片側だけだと段が反転する`,
    );
  }
  console.error(`\n${THEME} に不足側を書くか、上書き側を外してください。`);
  process.exit(1);
}
console.log(`slot-pairs: OK — overrideTogether の対 ${together} 件すべて、両方上書きか両方既定`);
