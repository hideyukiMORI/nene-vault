/**
 * Button — the kit's button, rendered against **this product's** theme.
 *
 * 🔴 This is a HARNESS, not a screen. It renders the kit primitive with this theme's slot
 * values loaded (`.storybook/preview.ts` imports `shared/ui/theme/index.css`), which is
 * enough to see what a slot value paints. It is **not** evidence about any real page: the
 * cascade, specificity, containing block and layer order of an actual screen are not
 * reproduced here. A screenshot taken from this file must never be filed as "checked in
 * production" — see `docs/qa/owner-review/README.md` for what the owner's bundle requires.
 *
 * Why it exists (2026-08-28, nene2-ui 0.20.0, #483):
 *   - The kit ships no visual harness of its own — measured across the kit's package:
 *     zero stories, zero demo pages. `warn` / `success` / `info` shipped as paint-able tones
 *     in 0.20.0 and, at the time this was written, **no ship rendered them even once**, so
 *     nobody had seen them. The matrix below is the first look.
 *   - None of this product's eight automated checks can see a wrong slot name. Measured by
 *     mutation on 2026-08-28: renaming `--shadow-x-slot-button-solid` back to the dead
 *     `-primary`, and deleting `--color-on-danger`, each left type-check, lint, format,
 *     stylelint, slot-values, slot-pairs, registries:check and all 241 unit tests green.
 *     (Positive controls did go red, so that green is a real result and not a broken
 *     harness.) A dead slot name is invisible to every check; it is only visible to an eye.
 */
import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from '@hideyukimori/nene2-ui';

const meta: Meta<typeof Button> = {
  title: 'Primitives/Button',
  component: Button,
  parameters: {
    docs: {
      description: {
        component:
          'HARNESS — kit primitive on this product’s theme. Not a screen; not evidence about production.',
      },
    },
  },
};

export default meta;
type Story = StoryObj<typeof Button>;

const SHAPES = ['solid', 'outline', 'bare', 'link'] as const;
const TONES = ['neutral', 'accent', 'danger', 'success', 'warn', 'info'] as const;

/**
 * The whole 4 × 6 surface, so a tone that reads wrong is visible next to the ones that read
 * right. `solid` and `outline` are the two shapes this product renders today.
 */
export const TwoAxisMatrix: Story = {
  render: () => (
    <table className="border-separate border-spacing-3 font-sans text-sm">
      <thead>
        <tr>
          <th className="text-left font-normal text-text-muted"> </th>
          {TONES.map((tone) => (
            <th key={tone} className="text-left font-normal text-text-muted">
              {tone}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {SHAPES.map((variant) => (
          <tr key={variant}>
            <th className="text-left font-normal text-text-muted">{variant}</th>
            {TONES.map((tone) => (
              <td key={tone}>
                <Button variant={variant} tone={tone}>
                  保存
                </Button>
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  ),
};

/**
 * The two call sites the 0.20.0 upgrade actually moves, with the exact props they carry in
 * `VoidModal.tsx` and `DocumentDetailPage.tsx`.
 *
 * What to look at:
 *   1. The filled danger button now casts `shadow-sm`. In 0.19.0 the shadow slot reached
 *      one variant (`primary`); in 0.20.0 the renamed slot reaches `solid` in every tone, so
 *      this product's two danger buttons gained the shadow its accent buttons already had.
 *   2. Its visible border colour is gone. The geometry does not move — `border` and
 *      `border-transparent` are in the kit's BASE_CLASS in both versions, byte-identical —
 *      only `border-x-slot-button-danger-border` drops out of the composed class list, so
 *      the transparent border underneath is what shows. There is no layout shift to find.
 *   3. The label is this theme's warm off-white, not the contract's neutral white. That is
 *      `--color-on-danger`, defined in this theme because 0.20.0 repointed
 *      `--color-x-slot-button-danger-fg` at it without renaming the slot.
 */
export const VaultCallSites: Story = {
  render: () => (
    <div className="flex flex-col gap-6 font-sans">
      <section className="flex flex-col gap-2">
        <p className="text-sm text-text-muted">VoidModal.tsx — 無効化の確定（破壊的操作）</p>
        <div className="flex items-center gap-2">
          <Button type="button" variant="outline" tone="neutral">
            キャンセル
          </Button>
          <Button type="submit" tone="danger">
            無効化する
          </Button>
        </div>
      </section>

      <section className="flex flex-col gap-2">
        <p className="text-sm text-text-muted">DocumentDetailPage.tsx — 文書詳細のツールバー</p>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" tone="neutral">
            ダウンロード
          </Button>
          <Button variant="outline" tone="neutral">
            OCR 提案
          </Button>
          <Button variant="outline" tone="neutral">
            変更を保存
          </Button>
          <Button tone="danger">無効化</Button>
        </div>
      </section>

      <section className="flex flex-col gap-2">
        <p className="text-sm text-text-muted">
          既定（tone を書かない）＝ accent。この製品の 11 箇所がこれ
        </p>
        <div className="flex items-center gap-2">
          <Button>設定を保存</Button>
          <Button size="sm">保存（sm）</Button>
        </div>
      </section>
    </div>
  ),
};

/**
 * 375px — the width the owner's NG came back on twice during W1b.
 *
 * 🔴 The labels here are the real `ja.json` strings these buttons render, not invented ones.
 * The longest label any `<Button>` in this product renders is 10 characters
 * (`common.status.uploading`, アップロード中...), measured 2026-08-28 by reading the `t()` keys
 * out of all 25 call sites; a harness with shorter labels than the product would prove
 * nothing about wrapping.
 *
 * fleet #501 is open: the kit's Button carries no `white-space: nowrap`, and nene-clear
 * measured labels breaking mid-word on narrow screens. This story is where to look before
 * deciding whether this product needs the same stopgap; a stopgap is only worth adding once
 * something has actually been seen to break, because otherwise nobody can ever say when it
 * is safe to remove.
 *
 * 🔴 This story does not set its own width. A device width is not a value this design system
 * carries — there is no token for it, an arbitrary Tailwind value is banned here (R1⑤), and
 * the `style` prop takes CSS-variable keys only (R5 AM-8(f)). So the width comes from the
 * browser: capture it with the viewport set to 375, which is also how a phone would deliver
 * it. `tools/shoot-button-harness.mjs` does exactly that.
 */
export const NarrowViewport: Story = {
  parameters: { viewport: { defaultViewport: 'mobile1' } },
  render: () => (
    <div className="flex flex-col gap-3 border border-border p-3 font-sans">
      <div className="flex flex-wrap items-center gap-2">
        <Button variant="outline" tone="neutral">
          変更を保存
        </Button>
        <Button variant="outline" tone="neutral">
          OCR 提案
        </Button>
        <Button tone="danger">無効化</Button>
      </div>
      <div className="flex flex-col-reverse gap-2.5">
        <Button variant="outline" tone="neutral">
          キャンセル
        </Button>
        <Button tone="danger">無効化する</Button>
      </div>
    </div>
  ),
};
