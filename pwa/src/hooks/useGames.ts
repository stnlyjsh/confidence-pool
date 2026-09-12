import { useQuery } from '@tanstack/react-query'
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
