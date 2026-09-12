import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'

export interface Pool {
  id: number
  name: string
  season_year: number
  status: 'draft' | 'active' | 'completed'
  buy_in_amount_cents: number
  weekly_payout_cents: number
  season_payout_cents: number
  invite_code?: string
}

interface Envelope<T> {
  data: T
}

const POOL_KEY = ['pool']

export function usePool() {
  return useQuery({
    queryKey: POOL_KEY,
    queryFn: () => api.get<Envelope<Pool>>('/api/pool').then((res) => res.data),
  })
}

export function useUpdatePool() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (changes: Partial<Pick<Pool, 'name' | 'season_year' | 'buy_in_amount_cents' | 'weekly_payout_cents' | 'season_payout_cents' | 'status'>>) =>
      api.patch<Envelope<Pool>>('/api/pool', changes).then((res) => res.data),
    onSuccess: (pool) => queryClient.setQueryData(POOL_KEY, pool),
  })
}

export function useRegenerateInvite() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<Envelope<Pool>>('/api/pool/invite/regenerate').then((res) => res.data),
    onSuccess: (pool) => queryClient.setQueryData(POOL_KEY, pool),
  })
}
