import { waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockAuditEvent, mockDocumentHistory } from '@tests/msw/fixtures';
import { useAuditEvents, useDocumentHistory } from './queries';

describe('useAuditEvents', () => {
  it('returns paginated audit events', async () => {
    const { result } = renderHookWithProviders(() => useAuditEvents({}));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.items).toHaveLength(1);
    expect(result.current.data?.items[0]?.action).toBe('document.uploaded');
    expect(result.current.data?.total).toBe(1);
  });

  it('returns correct event shape', async () => {
    const { result } = renderHookWithProviders(() => useAuditEvents({}));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    const event = result.current.data?.items[0];
    expect(event?.entity_type).toBe(mockAuditEvent.entity_type);
    expect(event?.entity_id).toBe(DOCUMENT_ID);
    expect(event?.actor_user_id).toBe(1);
  });

  it('passes filter params (action)', async () => {
    const { result } = renderHookWithProviders(() =>
      useAuditEvents({ action: 'document.uploaded' }),
    );

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });
    expect(result.current.data?.items[0]?.action).toBe('document.uploaded');
  });
});

describe('useDocumentHistory', () => {
  it('returns versions and audit events for a document', async () => {
    const { result } = renderHookWithProviders(() => useDocumentHistory(DOCUMENT_ID));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.versions).toHaveLength(1);
    expect(result.current.data?.versions[0]?.version_number).toBe(1);
    expect(result.current.data?.audit_events).toHaveLength(mockDocumentHistory.audit_events.length);
  });

  it('is in error state for an unknown document id', async () => {
    const { result } = renderHookWithProviders(() => useDocumentHistory('no-such-doc'));

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});
