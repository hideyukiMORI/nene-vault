/**
 * Input — single-line text field.
 *
 * In:  type, value, placeholder, disabled, aria-*
 * Out: onChange(event), onBlur(event)
 *
 * Does not: validate, fetch, or know entity shapes.
 */
import type { Meta, StoryObj } from '@storybook/react';
import { Input } from './Input';

const meta: Meta<typeof Input> = {
  title: 'Primitives/Input',
  component: Input,
  args: { placeholder: 'admin@example.com' },
};

export default meta;
type Story = StoryObj<typeof Input>;

export const Default: Story = {};
export const Disabled: Story = { args: { disabled: true } };
