import { type MessageLogEntry } from '@/lib/api';
import { useListResource } from './use-list-resource';

export interface MessageLogFilters {
  channel: string;
  status: string;
  recipient: string;
  gateway_id: string;
  date_from: string;
  date_to: string;
}

export interface UseMessageLogsReturn {
  logs: MessageLogEntry[];
  total: number;
  page: number;
  perPage: number;
  filters: MessageLogFilters;
  setFilter: (key: keyof MessageLogFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
}

export function useMessageLogs(perPage = 20): UseMessageLogsReturn {
  const list = useListResource<MessageLogEntry, MessageLogFilters>({
    endpoint: 'message-logs',
    defaultFilters: { channel: '', status: '', recipient: '', gateway_id: '', date_from: '', date_to: '' },
    perPage,
  });

  return {
    logs: list.items, total: list.total, page: list.page, perPage: list.perPage,
    filters: list.filters, setFilter: list.setFilter, setPage: list.setPage, loading: list.loading,
  };
}
