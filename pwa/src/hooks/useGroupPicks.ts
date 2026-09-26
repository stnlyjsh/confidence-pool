import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import type { Team } from './useGames'

export interface GroupPickEntry {
  user_id: number
  name: string
  has_picked: boolean
  revealed: boolean
  picked_team_id: number | null
  confidence_value: number | null
  is_auto_assigned: boolean | null
}

export interface GroupPickGame {
  game_id: number
  kickoff_at: string
  is_locked: boolean
  home_team: Team
  away_team: Team
  picks: GroupPickEntry[]
}

export function useGroupPicks(season: number, week: number) {
  return useQuery({
    queryKey: ['group-picks', season, week],
    queryFn: () =>
      api.get<{ data: GroupPickGame[] }>(`/api/weeks/${season}/${week}/group-picks`).then((res) => res.data),
  })
}
