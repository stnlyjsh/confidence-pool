import { WeekNav } from '../components/WeekNav'
import { useGroupPicks, type GroupPickEntry, type GroupPickGame } from '../hooks/useGroupPicks'
import { useWeekSelection } from '../hooks/useWeekSelection'
import type { Team } from '../hooks/useGames'

function teamLabel(team: Team): string {
  return team.city ?? team.abbreviation
}

function kickoffLabel(game: GroupPickGame): string {
  if (game.is_locked) return 'Locked'
  return new Date(game.kickoff_at).toLocaleString(undefined, {
    weekday: 'short',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function PickCell({ entry, game }: { entry: GroupPickEntry; game: GroupPickGame }) {
  if (!entry.revealed) {
    return (
      <span className="text-xs text-slate-400">{entry.has_picked ? '🔒 Picked' : 'Not picked yet'}</span>
    )
  }

  if (!entry.picked_team_id) {
    return (
      <span className="text-xs text-slate-400">{entry.is_auto_assigned ? 'No pick (auto)' : 'No pick'}</span>
    )
  }

  const team = entry.picked_team_id === game.home_team.id ? game.home_team : game.away_team

  return (
    <span className="flex items-center gap-2">
      <span className="text-sm">{teamLabel(team)}</span>
      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
        {entry.confidence_value}
      </span>
    </span>
  )
}

function GroupGameCard({ game }: { game: GroupPickGame }) {
  return (
    <div className="rounded-lg border border-slate-200 dark:border-slate-700">
      <div className="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
        <span className="text-sm font-medium">
          {game.away_team.abbreviation} @ {game.home_team.abbreviation}
        </span>
        <span className="ml-2 text-xs text-slate-500">{kickoffLabel(game)}</span>
      </div>
      <div className="divide-y divide-slate-100 dark:divide-slate-800">
        {game.picks.map((entry) => (
          <div key={entry.user_id} className="flex items-center justify-between px-3 py-2">
            <span className="text-sm font-medium">{entry.name}</span>
            <PickCell entry={entry} game={game} />
          </div>
        ))}
      </div>
    </div>
  )
}

export function GroupPicksPage() {
  const { season, week, setWeek, isLoadingCurrentWeek } = useWeekSelection()
  const { data: games, isLoading, isError } = useGroupPicks(season, week)

  return (
    <div className="flex-1 px-4 py-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Group Picks</h1>
        <WeekNav season={season} week={week} onChangeWeek={setWeek} />
      </div>

      {(isLoadingCurrentWeek || isLoading) && <p className="text-sm text-slate-500">Loading…</p>}
      {isError && <p className="text-sm text-red-600 dark:text-red-400">Couldn't load group picks.</p>}
      {games && games.length === 0 && (
        <p className="text-sm text-slate-500">No games synced for this week yet.</p>
      )}

      <div className="space-y-3">
        {games?.map((game) => (
          <GroupGameCard key={game.game_id} game={game} />
        ))}
      </div>
    </div>
  )
}
