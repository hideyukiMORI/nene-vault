import { useState } from 'react';
import { apiClient } from '@/shared/api/client';

interface OcrSuggestionResponse {
  document_id: string;
  transaction_date: string | null;
  amount_cents: number | null;
  counterparty_name: string | null;
  has_suggestion: boolean;
}

export interface OcrPrefill {
  transaction_date?: string | null;
  amount_cents?: number | null;
  counterparty_name?: string | null;
}

export function useOcrSuggest() {
  const [isLoading, setIsLoading] = useState(false);

  async function suggest(documentId: string): Promise<OcrPrefill | null> {
    setIsLoading(true);
    try {
      const result = await apiClient.get<OcrSuggestionResponse>(
        `/admin/vault/documents/${documentId}/ocr-suggest`,
      );
      return {
        transaction_date: result.transaction_date,
        amount_cents: result.amount_cents,
        counterparty_name: result.counterparty_name,
      };
    } catch {
      return null;
    } finally {
      setIsLoading(false);
    }
  }

  return { suggest, isLoading };
}
