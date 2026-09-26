import { WeekNav } from '../components/WeekNav'
import { useGroupPicks, type GroupPickEntry, type GroupPickGame } from '../hooks/useGroupPicks'
import { useWeekSelection } from '../hooks/useWeekSelection'
import type { Team } from '../hooks/useGames'

function matchupKickoffLabel(game: GroupPickGame): string {
  if (game.is_locked) return 'Locked'
  return new Date(game.kickoff_at).toLocaleString(undefined, {
    weekday: 'short',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function TeamLogo({ team, className }: { team: Team; className: string }) {
  if (!team.logo_url) {
    return <span className="text-[10px] font-semibold text-slate-500">{team.abbreviation}</span>
  }
  return <img src={team.logo_url} alt={team.abbreviation} className={className} />
}

function MatchupHeader({ game }: { game: GroupPickGame }) {
  return (
    <div className="flex w-16 flex-col items-center gap-0.5 px-1 py-2">
      <TeamLogo team={game.away_team} className="h-5 w-5 object-contain" />
      <span className="text-[9px] leading-none text-slate-400">@</span>
      <TeamLogo team={game.home_team} className="h-5 w-5 object-contain" />
      <span className="mt-1 text-center text-[9px] leading-tight whitespace-nowrap text-slate-500">
        {matchupKickoffLabel(game)}
      </span>
    </div>
  )
}

function PickCell({ entry, game }: { entry: GroupPickEntry; game: GroupPickGame }) {
  if (!entry.revealed) {
    return (
      <span className="text-xs text-slate-400" title={entry.has_picked ? 'Picked' : 'Not picked yet'}>
        {entry.has_picked ? '🔒' : '–'}
      </span>
    )
  }

  if (!entry.picked_team_id) {
    return (
      <span className="text-xs text-slate-400" title={entry.is_auto_assigned ? 'No pick (auto)' : 'No pick'}>
        ✕
      </span>
    )
  }

  const team = entry.picked_team_id === game.home_team.id ? game.home_team : game.away_team

  return (
    <span className="relative inline-flex h-8 w-8 items-center justify-center">
      <TeamLogo team={team} className="h-7 w-7 object-contain" />
      <span className="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-semibold text-white">
        {entry.confidence_value}
      </span>
    </span>
  )
}

export function GroupPicksPage() {
  const { season, week, setWeek, isLoadingCurrentWeek } = useWeekSelection()
  const { data: games, isLoading, isError } = useGroupPicks(season, week)

  const users = games?.[0]?.picks.map((entry) => ({ user_id: entry.user_id, name: entry.name })) ?? []

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

      {games && games.length > 0 && (
        <div className="-mx-4 overflow-x-auto px-4">
          <table className="border-separate border-spacing-0">
            <thead>
              <tr>
                <th className="sticky left-0 top-0 z-20 border-b border-r border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900" />
                {games.map((game) => (
                  <th
                    key={game.game_id}
                    className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 font-normal dark:border-slate-700 dark:bg-slate-900"
                  >
                    <MatchupHeader game={game} />
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.user_id}>
                  <th
                    scope="row"
                    className="sticky left-0 z-10 whitespace-nowrap border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm font-medium dark:border-slate-700 dark:bg-slate-900"
                  >
                    {user.name}
                  </th>
                  {games.map((game) => {
                    const entry = game.picks.find((p) => p.user_id === user.user_id)
                    return (
                      <td
                        key={game.game_id}
                        className="border-b border-slate-100 px-1 py-2 text-center dark:border-slate-800"
                      >
                        {entry && <PickCell entry={entry} game={game} />}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
