import { useState } from 'react'
import { WeekNav } from '../components/WeekNav'
import { useSeasonStandings, useWeekStandings, type StandingsRow } from '../hooks/useStandings'
import { defaultSeasonYear } from '../lib/nflSeason'

function StandingsTable({ rows }: { rows: StandingsRow[] }) {
  if (rows.length === 0) {
    return <p className="text-sm text-slate-500">No standings yet.</p>
  }

  return (
    <div className="space-y-1">
      {rows.map((row, index) => (
        <div
          key={row.user_id}
          className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700"
        >
          <div className="flex items-center gap-3">
            <span className="w-5 text-sm text-slate-400">{index + 1}</span>
            <span className="text-sm font-medium">{row.name}</span>
          </div>
          <div className="flex items-baseline gap-1">
            <span className="text-sm font-semibold tabular-nums">{row.total_points}</span>
            <span className="text-xs text-slate-500">pts</span>
          </div>
        </div>
      ))}
    </div>
  )
}

export function StandingsPage() {
  const [scope, setScope] = useState<'season' | 'week'>('season')
  const [season] = useState(defaultSeasonYear())
  const [week, setWeek] = useState(1)

  const seasonStandings = useSeasonStandings()
  const weekStandings = useWeekStandings(week)

  const active = scope === 'season' ? seasonStandings : weekStandings

  return (
    <div className="flex-1 px-4 py-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Standings</h1>
        <div className="flex overflow-hidden rounded-lg border border-slate-300 text-xs dark:border-slate-700">
          <button
            onClick={() => setScope('season')}
            className={`px-3 py-1.5 ${scope === 'season' ? 'bg-indigo-600 text-white' : ''}`}
          >
            Season
          </button>
          <button
            onClick={() => setScope('week')}
            className={`px-3 py-1.5 ${scope === 'week' ? 'bg-indigo-600 text-white' : ''}`}
          >
            Week
          </button>
        </div>
      </div>

      {scope === 'week' && (
        <div className="mb-4 flex justify-end">
          <WeekNav season={season} week={week} onChangeWeek={setWeek} />
        </div>
      )}

      {active.isLoading && <p className="text-sm text-slate-500">Loading…</p>}
      {active.isError && <p className="text-sm text-red-600 dark:text-red-400">Couldn't load standings.</p>}
      {active.data && <StandingsTable rows={active.data} />}
    </div>
  )
}
