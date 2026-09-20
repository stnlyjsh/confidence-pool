import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'

export interface LedgerEntry {
  id: number
  user_id: number
  user_name?: string
  season: number
  week: number | null
  type: 'buy_in' | 'weekly_payout' | 'season_payout' | 'adjustment'
  amount_cents: number
  is_paid: boolean
  paid_at: string | null
  notes: string | null
}

export function useMyLedger() {
  return useQuery({
    queryKey: ['ledger', 'mine'],
    queryFn: () => api.get<{ data: LedgerEntry[] }>('/api/ledger').then((res) => res.data),
  })
}

export function useAllLedger() {
  return useQuery({
    queryKey: ['ledger', 'all'],
    queryFn: () => api.get<{ data: LedgerEntry[] }>('/api/ledger/all').then((res) => res.data),
  })
}

export function useMarkPaid() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (entryId: number) => api.post<{ data: LedgerEntry }>(`/api/ledger/${entryId}/mark-paid`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ledger'] })
    },
  })
}

export function useCloseWeek() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (week: number) => api.post(`/api/admin/close-week/${week}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['ledger'] }),
  })
}

export function useCloseSeason() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post('/api/admin/close-season'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['ledger'] }),
  })
}
