/**
 * Button — primary action control.
 *
 * In:  variant ('primary' | 'secondary' | 'danger'), disabled, type, children
 * Out: onClick(event)
 *
 * Does not: fetch data, know entity ids, or read router/query cache.
 */
import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './Button';

const meta: Meta<typeof Button> = {
  title: 'Primitives/Button',
  component: Button,
  args: { children: 'Save' },
};

export default meta;
type Story = StoryObj<typeof Button>;

export const Primary: Story = { args: { variant: 'primary' } };
export const Secondary: Story = { args: { variant: 'secondary' } };
export const Danger: Story = { args: { variant: 'danger' } };
export const Disabled: Story = { args: { disabled: true } };
