import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'

export interface StandingsRow {
  user_id: number
  name: string
  total_points: number
  correct_count: number
  pct_correct: number | null
  max_potential_points: number
}

export function useSeasonStandings() {
  return useQuery({
    queryKey: ['standings', 'season'],
    queryFn: () => api.get<{ data: StandingsRow[] }>('/api/standings/season').then((res) => res.data),
  })
}

export function useWeekStandings(week: number) {
  return useQuery({
    queryKey: ['standings', 'week', week],
    queryFn: () => api.get<{ data: StandingsRow[] }>(`/api/standings/weeks/${week}`).then((res) => res.data),
  })
}
