import { useMemo, useState } from 'react'
import { WeekNav } from '../components/WeekNav'
import { useSeasonStandings, useWeekStandings, type StandingsRow } from '../hooks/useStandings'
import { useWeekSelection } from '../hooks/useWeekSelection'

type SortKey = 'name' | 'total_points' | 'max_potential_points' | 'pct_correct'

const columns: { key: SortKey; label: string; defaultDirection: 'asc' | 'desc' }[] = [
  { key: 'name', label: 'Player', defaultDirection: 'asc' },
  { key: 'total_points', label: 'Points', defaultDirection: 'desc' },
  { key: 'max_potential_points', label: 'Max', defaultDirection: 'desc' },
  { key: 'pct_correct', label: '% Correct', defaultDirection: 'desc' },
]

function sortValue(row: StandingsRow, key: SortKey): string | number {
  if (key === 'name') return row.name.toLowerCase()
  const value = row[key]
  return value ?? -Infinity
}

function useSortedRows(rows: StandingsRow[] | undefined, sort: { key: SortKey; direction: 'asc' | 'desc' }) {
  return useMemo(() => {
    if (!rows) return []
    const sorted = [...rows].sort((a, b) => {
      const aValue = sortValue(a, sort.key)
      const bValue = sortValue(b, sort.key)
      if (typeof aValue === 'string' || typeof bValue === 'string') {
        return String(aValue).localeCompare(String(bValue))
      }
      return aValue - bValue
    })
    if (sort.direction === 'desc') sorted.reverse()
    return sorted
  }, [rows, sort])
}

function StandingsTable({ rows }: { rows: StandingsRow[] }) {
  const [sort, setSort] = useState<{ key: SortKey; direction: 'asc' | 'desc' }>({
    key: 'total_points',
    direction: 'desc',
  })

  const sortedRows = useSortedRows(rows, sort)

  function toggleSort(key: SortKey) {
    setSort((prev) =>
      prev.key === key
        ? { key, direction: prev.direction === 'asc' ? 'desc' : 'asc' }
        : { key, direction: columns.find((c) => c.key === key)!.defaultDirection },
    )
  }

  if (rows.length === 0) {
    return <p className="text-sm text-slate-500">No standings yet.</p>
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-slate-200 dark:border-slate-700">
            {columns.map((col) => (
              <th
                key={col.key}
                onClick={() => toggleSort(col.key)}
                className={`cursor-pointer select-none whitespace-nowrap py-2 text-xs font-medium text-slate-500 ${
                  col.key === 'name' ? 'text-left' : 'text-right'
                }`}
              >
                {col.label}
                {sort.key === col.key && <span className="ml-1">{sort.direction === 'asc' ? '▲' : '▼'}</span>}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {sortedRows.map((row, index) => (
            <tr key={row.user_id} className="border-b border-slate-100 dark:border-slate-800">
              <td className="py-2.5 text-left">
                <span className="mr-2 text-xs text-slate-400">{index + 1}</span>
                <span className="font-medium">{row.name}</span>
              </td>
              <td className="py-2.5 text-right font-semibold tabular-nums">{row.total_points}</td>
              <td className="py-2.5 text-right tabular-nums text-slate-500">{row.max_potential_points}</td>
              <td className="py-2.5 text-right tabular-nums text-slate-500">
                {row.pct_correct !== null ? `${row.pct_correct}%` : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function StandingsPage() {
  const [scope, setScope] = useState<'season' | 'week'>('week')
  const { season, week, setWeek, isLoadingCurrentWeek } = useWeekSelection()

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

      {(isLoadingCurrentWeek || active.isLoading) && <p className="text-sm text-slate-500">Loading…</p>}
      {active.isError && <p className="text-sm text-red-600 dark:text-red-400">Couldn't load standings.</p>}
      {active.data && <StandingsTable rows={active.data} />}
    </div>
  )
}
