import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'

export interface Team {
  id: number
  abbreviation: string
  name: string
  logo_url: string | null
}

export interface Game {
  id: number
  season: number
  week: number
  kickoff_at: string
  is_locked: boolean
  status: 'scheduled' | 'in_progress' | 'final' | 'postponed' | 'canceled' | 'voided'
  home_team: Team
  away_team: Team
  home_score: number | null
  away_score: number | null
}

export function useGames(season: number, week: number) {
  return useQuery({
    queryKey: ['games', season, week],
    queryFn: () => api.get<{ data: Game[] }>(`/api/weeks/${season}/${week}/games`).then((res) => res.data),
  })
}

export interface GameOverride {
  status: 'final' | 'postponed' | 'canceled'
  home_score?: number
  away_score?: number
}

export function useVoidGame() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (gameId: number) => api.post(`/api/admin/games/${gameId}/void`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['games'] }),
  })
}

export function useOverrideGame() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ gameId, ...body }: GameOverride & { gameId: number }) =>
      api.patch(`/api/admin/games/${gameId}`, body),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['games'] }),
  })
}
