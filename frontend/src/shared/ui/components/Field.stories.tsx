/**
 * Field — labelled form field wrapper (label + control + optional hint/error).
 *
 * In:  label, required, hint, error, labelTone, children (the control)
 * Out: none (purely presentational; the control inside owns its own events)
 *
 * Does not: register with a form, validate, or fetch.
 */
import type { Meta, StoryObj } from '@storybook/react';
import { Field } from './Field';
import { Input } from '../primitives/Input';
import { I18nProvider } from '@/shared/i18n/i18n-context';

const meta: Meta<typeof Field> = {
  title: 'Components/Field',
  component: Field,
  decorators: [
    (Story) => (
      <I18nProvider>
        <div className="w-80">
          <Story />
        </div>
      </I18nProvider>
    ),
  ],
  render: (args) => (
    <Field {...args}>
      <Input type="text" placeholder="Sample Inc." />
    </Field>
  ),
};

export default meta;
type Story = StoryObj<typeof Field>;

export const Default: Story = { args: { label: 'Counterparty' } };
export const Required: Story = { args: { label: 'Counterparty', required: true } };
export const WithHint: Story = {
  args: { label: 'Counterparty', hint: 'As printed on the document' },
};
export const WithError: Story = {
  args: { label: 'Counterparty', error: 'This field is required.' },
};
export const MutedLabel: Story = { args: { label: 'Counterparty', labelTone: 'muted' } };
