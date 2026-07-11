/**
 * BrandMark — the NeNe Vault seal (印影 × 鍵穴).
 *
 * In:  size, simplified, title, className
 * Out: nothing — presentational SVG; inherits color from `currentColor`.
 *
 * Does not: fetch data, read router/query cache, or hard-code its color.
 */
import type { Meta, StoryObj } from '@storybook/react-vite';
import { BrandMark } from './BrandMark';

const meta: Meta<typeof BrandMark> = {
  title: 'Primitives/BrandMark',
  component: BrandMark,
  args: { size: 64, title: 'NeNe Vault' },
};

export default meta;
type Story = StoryObj<typeof BrandMark>;

export const Default: Story = { args: { className: 'text-seal' } };
export const Simplified: Story = { args: { simplified: true, size: 48, className: 'text-seal' } };
export const OnDark: Story = {
  args: { className: 'text-seal-bright' },
  decorators: [
    (Story) => (
      <div className="bg-rail p-lg">
        <Story />
      </div>
    ),
  ],
};
