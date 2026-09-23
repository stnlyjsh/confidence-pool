import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { getEcho } from '../lib/echo'
import type { Game } from './useGames'
import { usePool } from './usePool'

interface GameScoreUpdatedPayload {
  game_id: number
  home_score: number | null
  away_score: number | null
  status: Game['status']
}

interface StandingsUpdatedPayload {
  week: number
}

// Subscribes to the pool's private channel once authenticated. Score
// updates patch the games cache directly (cheap, snappy); standings are
// just a signal to refetch since recomputing them client-side isn't worth
// the duplicated logic.
export function useRealtimeUpdates() {
  const { data: pool } = usePool()
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!pool) return

    const echo = getEcho()
    const channel = echo.private(`pool.${pool.id}`)

    channel.listen('.GameScoreUpdated', (event: GameScoreUpdatedPayload) => {
      queryClient.setQueriesData({ queryKey: ['games'] }, (games: Game[] | undefined) =>
        games?.map((game) =>
          game.id === event.game_id
            ? { ...game, home_score: event.home_score, away_score: event.away_score, status: event.status }
            : game,
        ),
      )
    })

    channel.listen('.StandingsUpdated', (_event: StandingsUpdatedPayload) => {
      queryClient.invalidateQueries({ queryKey: ['standings'] })
    })

    return () => {
      echo.leave(`pool.${pool.id}`)
    }
  }, [pool?.id, queryClient])
}
