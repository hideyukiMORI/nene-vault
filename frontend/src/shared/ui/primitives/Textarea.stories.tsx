/**
 * Textarea — multi-line text field.
 *
 * In:  value, rows, placeholder, disabled, aria-*
 * Out: onChange(event), onBlur(event)
 *
 * Does not: validate, fetch, or know entity shapes.
 */
import type { Meta, StoryObj } from '@storybook/react';
import { Textarea } from './Textarea';

const meta: Meta<typeof Textarea> = {
  title: 'Primitives/Textarea',
  component: Textarea,
  args: { placeholder: 'Optional note…', rows: 3 },
};

export default meta;
type Story = StoryObj<typeof Textarea>;

export const Default: Story = {};
export const Disabled: Story = { args: { disabled: true } };
