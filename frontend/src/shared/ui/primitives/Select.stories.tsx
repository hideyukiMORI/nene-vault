/**
 * Select — single-choice dropdown.
 *
 * In:  value, disabled, children (<option>), aria-*
 * Out: onChange(event)
 *
 * Does not: validate, fetch, or know entity shapes.
 */
import type { Meta, StoryObj } from '@storybook/react';
import { Select } from './Select';

const meta: Meta<typeof Select> = {
  title: 'Primitives/Select',
  component: Select,
  render: (args) => (
    <Select {...args}>
      <option value="invoice_received">Invoice Received</option>
      <option value="contract">Contract</option>
      <option value="receipt">Receipt</option>
    </Select>
  ),
};

export default meta;
type Story = StoryObj<typeof Select>;

export const Default: Story = {};
export const Disabled: Story = { args: { disabled: true } };
