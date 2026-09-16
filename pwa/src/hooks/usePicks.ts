import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'

export interface Pick {
  game_id: number
  picked_team_id: number | null
  confidence_value: number
  is_auto_assigned: boolean
  is_locked: boolean
}

export interface RankedPick {
  game_id: number
  picked_team_id: number
}

function picksKey(season: number, week: number) {
  return ['picks', season, week]
}

export function usePicks(season: number, week: number) {
  return useQuery({
    queryKey: picksKey(season, week),
    queryFn: () => api.get<{ data: Pick[] }>(`/api/weeks/${season}/${week}/picks`).then((res) => res.data),
  })
}

export function useSavePicks(season: number, week: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (picks: RankedPick[]) =>
      api.put<{ data: Pick[] }>(`/api/weeks/${season}/${week}/picks`, { picks }).then((res) => res.data),
    onSuccess: (picks) => queryClient.setQueryData(picksKey(season, week), picks),
  })
}
