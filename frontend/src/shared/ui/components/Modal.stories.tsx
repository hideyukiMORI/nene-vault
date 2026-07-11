/**
 * Modal — centered dialog (overlay + panel + optional header/close).
 *
 * In:  title, size, children
 * Out: onClose()
 *
 * Does not: manage focus-trap routing, fetch, or know entity shapes.
 */
import type { Meta, StoryObj } from '@storybook/react-vite';
import { Modal } from './Modal';
import { Button } from '../primitives/Button';
import { Stack } from '../primitives/Stack';
import { Text } from '../primitives/Text';

const meta: Meta<typeof Modal> = {
  title: 'Components/Modal',
  component: Modal,
  args: { title: 'Confirm action', size: 'sm' },
  argTypes: { onClose: { action: 'close' } },
  render: (args) => (
    <Modal {...args}>
      <div className="p-inline-lg">
        <Stack gap="md">
          <Text tone="muted" className="text-body-sm">
            Modal body content goes here.
          </Text>
          <div className="flex justify-end gap-inline-md">
            <Button variant="secondary" onClick={args.onClose}>
              Cancel
            </Button>
            <Button variant="primary">Confirm</Button>
          </div>
        </Stack>
      </div>
    </Modal>
  ),
};

export default meta;
type Story = StoryObj<typeof Modal>;

export const WithHeader: Story = {};
export const Large: Story = { args: { size: 'lg' } };
export const NoHeader: Story = { args: { title: undefined } };
