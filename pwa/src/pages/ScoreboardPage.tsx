import { useState } from 'react'
import { WeekNav } from '../components/WeekNav'
import { useAuth } from '../context/AuthContext'
import { useGames, useOverrideGame, useVoidGame, type Game } from '../hooks/useGames'
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

function ManageGamePanel({ game, onClose }: { game: Game; onClose: () => void }) {
  const [status, setStatus] = useState<'final' | 'postponed' | 'canceled'>('final')
  const [homeScore, setHomeScore] = useState('')
  const [awayScore, setAwayScore] = useState('')
  const voidGame = useVoidGame()
  const overrideGame = useOverrideGame()

  function save() {
    overrideGame.mutate(
      {
        gameId: game.id,
        status,
        ...(status === 'final' ? { home_score: Number(homeScore), away_score: Number(awayScore) } : {}),
      },
      { onSuccess: onClose },
    )
  }

  return (
    <div className="mt-2 space-y-3 rounded-lg border border-dashed border-slate-300 p-3 text-sm dark:border-slate-600">
      <div>
        <p className="mb-1 text-xs font-medium text-slate-500">Manually set result (use if ESPN is down/wrong)</p>
        <div className="flex flex-wrap items-center gap-2">
          <select
            value={status}
            onChange={(e) => setStatus(e.target.value as typeof status)}
            className="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800"
          >
            <option value="final">Final</option>
            <option value="postponed">Postponed</option>
            <option value="canceled">Canceled</option>
          </select>
          {status === 'final' && (
            <>
              <input
                type="number"
                placeholder={game.away_team.abbreviation}
                value={awayScore}
                onChange={(e) => setAwayScore(e.target.value)}
                className="w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800"
              />
              <input
                type="number"
                placeholder={game.home_team.abbreviation}
                value={homeScore}
                onChange={(e) => setHomeScore(e.target.value)}
                className="w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800"
              />
            </>
          )}
          <button
            onClick={save}
            disabled={overrideGame.isPending}
            className="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-50"
          >
            Save
          </button>
        </div>
      </div>

      <div className="flex items-center justify-between border-t border-slate-200 pt-2 dark:border-slate-700">
        <button
          onClick={() => voidGame.mutate(game.id, { onSuccess: onClose })}
          disabled={voidGame.isPending || game.status === 'voided'}
          className="text-xs font-medium text-red-600 disabled:opacity-40 dark:text-red-400"
        >
          {game.status === 'voided' ? 'Already voided' : 'Void this game'}
        </button>
        <button onClick={onClose} className="text-xs text-slate-400">
          Close
        </button>
      </div>
    </div>
  )
}

function GameRow({ game, isCommissioner }: { game: Game; isCommissioner: boolean }) {
  const [managing, setManaging] = useState(false)
  const hasScore = game.home_score !== null && game.away_score !== null

  return (
    <div className="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
      <div className="flex items-center justify-between">
        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium">
            {game.away_team.abbreviation} @ {game.home_team.abbreviation}
          </span>
          <span className="text-xs text-slate-500">{statusLabel(game)}</span>
        </div>
        <div className="flex items-center gap-3">
          {hasScore && (
            <span className="text-sm font-semibold tabular-nums">
              {game.away_score}–{game.home_score}
            </span>
          )}
          {isCommissioner && (
            <button onClick={() => setManaging((m) => !m)} className="text-xs text-slate-400">
              Manage
            </button>
          )}
        </div>
      </div>
      {managing && <ManageGamePanel game={game} onClose={() => setManaging(false)} />}
    </div>
  )
}

export function ScoreboardPage() {
  const { user } = useAuth()
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
          <GameRow key={game.id} game={game} isCommissioner={user?.role === 'commissioner'} />
        ))}
      </div>
    </div>
  )
}
