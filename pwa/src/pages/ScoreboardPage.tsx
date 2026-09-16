import { useState } from 'react'
import { WeekNav } from '../components/WeekNav'
import { useGames, type Game } from '../hooks/useGames'
import { defaultSeasonYear } from '../lib/nflSeason'

function statusLabel(game: Game): string {
  switch (game.status) {
    case 'final':
      return 'Final'
    case 'in_progress':
      return 'In progress'
    case 'postponed':
      return 'Postponed'
    case 'canceled':
      return 'Canceled'
    case 'voided':
      return 'Voided'
    default:
      return new Date(game.kickoff_at).toLocaleString(undefined, {
        weekday: 'short',
        hour: 'numeric',
        minute: '2-digit',
      })
  }
}

function GameRow({ game }: { game: Game }) {
  const hasScore = game.home_score !== null && game.away_score !== null

  return (
    <div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium">
          {game.away_team.abbreviation} @ {game.home_team.abbreviation}
        </span>
        <span className="text-xs text-slate-500">{statusLabel(game)}</span>
      </div>
      {hasScore && (
        <span className="text-sm font-semibold tabular-nums">
          {game.away_score}–{game.home_score}
        </span>
      )}
    </div>
  )
}

export function ScoreboardPage() {
  const [season] = useState(defaultSeasonYear())
  const [week, setWeek] = useState(1)
  const { data: games, isLoading, isError } = useGames(season, week)

  return (
    <div className="flex-1 px-4 py-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Scoreboard</h1>
        <WeekNav season={season} week={week} onChangeWeek={setWeek} />
      </div>

      {isLoading && <p className="text-sm text-slate-500">Loading…</p>}
      {isError && <p className="text-sm text-red-600 dark:text-red-400">Couldn't load games.</p>}
      {games && games.length === 0 && (
        <p className="text-sm text-slate-500">No games synced for this week yet.</p>
      )}

      <div className="space-y-2">
        {games?.map((game) => (
          <GameRow key={game.id} game={game} />
        ))}
      </div>
    </div>
  )
}
